<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Services\SlackSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncSlackMessagesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 1800; // 30分タイムアウト
    public int $tries = 3; // 最大3回リトライ
    public int $maxExceptions = 3; // 最大例外数
    public int $backoff = 300; // 5分後にリトライ

    protected User $user;
    protected Workspace $workspace;
    protected array $channelIds;
    protected bool $fullSync;
    protected string $syncType;
    protected string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        User $user,
        Workspace $workspace,
        array $channelIds = [],
        bool $fullSync = false,
        string $syncType = 'manual'
    ) {
        $this->user = $user;
        $this->workspace = $workspace;
        $this->channelIds = $channelIds;
        $this->fullSync = $fullSync;
        $this->syncType = $syncType;
        $this->jobId = uniqid('sync_', true);
        
        // 個人制限を適用したキューに配置
        $this->onQueue('slack-sync-' . $user->id);
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // 同じユーザーの同期ジョブが同時実行されないようにする
            new WithoutOverlapping($this->user->id),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting Slack sync job', [
                'job_id' => $this->jobId,
                'user_id' => $this->user->id,
                'workspace_id' => $this->workspace->id,
                'channel_count' => count($this->channelIds),
                'full_sync' => $this->fullSync,
                'sync_type' => $this->syncType,
                'attempt' => $this->attempts()
            ]);

            // 進捗状況記録の初期化
            $this->initializeProgress();

            // SlackSyncService初期化
            $service = new SlackSyncService($this->user, $this->workspace);

            // 同期対象チャンネル決定
            $channels = $this->determineChannelsToSync($service);
            
            if ($channels->isEmpty()) {
                Log::warning('No accessible channels found for sync', [
                    'job_id' => $this->jobId,
                    'user_id' => $this->user->id
                ]);
                $this->completeJob([], 0);
                return;
            }

            // バッチ処理で同期実行
            $results = $this->processBatchSync($service, $channels);

            // 結果集計と記録
            $summary = $this->generateSummary($results, microtime(true) - $startTime);
            $this->completeJob($results, $summary['total_messages']);

            Log::info('Slack sync job completed successfully', array_merge([
                'job_id' => $this->jobId,
                'user_id' => $this->user->id
            ], $summary));

        } catch (Throwable $exception) {
            $this->handleJobException($exception, microtime(true) - $startTime);
            throw $exception; // Re-throw for queue retry mechanism
        }
    }

    /**
     * 同期対象チャンネルを決定
     */
    private function determineChannelsToSync(SlackSyncService $service): \Illuminate\Database\Eloquent\Collection
    {
        if (!empty($this->channelIds)) {
            // 指定されたチャンネルIDから、ユーザーがアクセス可能なもののみを取得
            return Channel::whereIn('id', $this->channelIds)
                ->where('workspace_id', $this->workspace->id)
                ->where(function ($query) {
                    if (!$this->user->is_admin) {
                        $query->where('is_private', false)
                            ->orWhereHas('users', function ($userQuery) {
                                $userQuery->where('users.id', $this->user->id);
                            });
                    }
                })
                ->get();
        }

        // 全アクセス可能チャンネルを取得
        return $this->getAllAccessibleChannels();
    }

    /**
     * ユーザーがアクセス可能な全チャンネルを取得
     */
    private function getAllAccessibleChannels(): \Illuminate\Database\Eloquent\Collection
    {
        $query = Channel::where('workspace_id', $this->workspace->id);

        if (!$this->user->is_admin) {
            $query->where(function ($q) {
                $q->where('is_private', false)
                  ->orWhereHas('users', function ($userQuery) {
                      $userQuery->where('users.id', $this->user->id);
                  });
            });
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    /**
     * バッチ処理で同期実行
     */
    private function processBatchSync(SlackSyncService $service, $channels): array
    {
        $results = [];
        $processed = 0;
        $total = $channels->count();

        foreach ($channels as $channel) {
            try {
                $this->updateProgress($processed, $total, $channel->name);

                // 個人制限を適用して同期実行
                $result = $service->syncChannel($channel, $this->fullSync);
                $results[] = $result;

                $processed++;

                Log::debug('Channel sync completed', [
                    'job_id' => $this->jobId,
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                    'success' => $result['success'],
                    'messages_saved' => $result['messages_saved'] ?? 0,
                    'progress' => "{$processed}/{$total}"
                ]);

                // レート制限対策：チャンネル間で少し待機
                if ($processed < $total) {
                    sleep(2);
                }

            } catch (Throwable $exception) {
                Log::error('Channel sync failed', [
                    'job_id' => $this->jobId,
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                    'error' => $exception->getMessage()
                ]);

                $results[] = [
                    'success' => false,
                    'channel' => $channel->name,
                    'error' => $exception->getMessage()
                ];

                $processed++;
            }
        }

        return $results;
    }

    /**
     * 進捗状況の初期化
     */
    private function initializeProgress(): void
    {
        $progressKey = "slack_sync_progress:{$this->user->id}:{$this->jobId}";
        
        Cache::put($progressKey, [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'status' => 'running',
            'progress' => 0,
            'total' => 0,
            'current_channel' => null,
            'started_at' => now()->toISOString(),
            'sync_type' => $this->syncType,
            'full_sync' => $this->fullSync
        ], 3600); // 1時間保持
    }

    /**
     * 進捗状況の更新
     */
    private function updateProgress(int $processed, int $total, ?string $currentChannel = null): void
    {
        $progressKey = "slack_sync_progress:{$this->user->id}:{$this->jobId}";
        
        Cache::put($progressKey, [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'status' => 'running',
            'progress' => $processed,
            'total' => $total,
            'current_channel' => $currentChannel,
            'started_at' => Cache::get($progressKey)['started_at'] ?? now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'sync_type' => $this->syncType,
            'full_sync' => $this->fullSync
        ], 3600);
    }

    /**
     * 結果サマリーの生成
     */
    private function generateSummary(array $results, float $executionTime): array
    {
        $successful = collect($results)->where('success', true);
        $failed = collect($results)->where('success', false);
        
        return [
            'total_channels' => count($results),
            'successful_channels' => $successful->count(),
            'failed_channels' => $failed->count(),
            'total_messages' => $successful->sum('messages_saved'),
            'execution_time' => round($executionTime, 2),
            'average_time_per_channel' => count($results) > 0 ? round($executionTime / count($results), 2) : 0,
            'failed_channel_names' => $failed->pluck('channel')->toArray()
        ];
    }

    /**
     * ジョブ完了処理
     */
    private function completeJob(array $results, int $totalMessages): void
    {
        $progressKey = "slack_sync_progress:{$this->user->id}:{$this->jobId}";
        
        Cache::put($progressKey, [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'status' => 'completed',
            'progress' => count($results),
            'total' => count($results),
            'current_channel' => null,
            'started_at' => Cache::get($progressKey)['started_at'] ?? now()->toISOString(),
            'completed_at' => now()->toISOString(),
            'sync_type' => $this->syncType,
            'full_sync' => $this->fullSync,
            'total_messages' => $totalMessages,
            'results' => $results
        ], 7200); // 2時間保持（完了後も参照可能）
    }

    /**
     * ジョブ例外処理
     */
    private function handleJobException(Throwable $exception, float $executionTime): void
    {
        Log::error('Slack sync job failed', [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'execution_time' => round($executionTime, 2),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // 進捗状況を失敗状態に更新
        $progressKey = "slack_sync_progress:{$this->user->id}:{$this->jobId}";
        $currentProgress = Cache::get($progressKey, []);
        
        Cache::put($progressKey, array_merge($currentProgress, [
            'status' => 'failed',
            'failed_at' => now()->toISOString(),
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts()
        ]), 7200);

        // 最終試行の場合は管理者に通知
        if ($this->attempts() >= $this->tries) {
            $this->notifyAdminOfFailure($exception);
        }
    }

    /**
     * 管理者への失敗通知
     */
    private function notifyAdminOfFailure(Throwable $exception): void
    {
        try {
            $admins = User::where('is_admin', true)->where('is_active', true)->get();
            
            foreach ($admins as $admin) {
                // 簡単なログ記録（実際の通知は別途実装）
                Log::warning('Admin notification: Slack sync job failed permanently', [
                    'job_id' => $this->jobId,
                    'failed_user_id' => $this->user->id,
                    'admin_id' => $admin->id,
                    'workspace_id' => $this->workspace->id,
                    'error' => $exception->getMessage()
                ]);
            }
        } catch (Throwable $notificationException) {
            Log::error('Failed to notify admins of sync failure', [
                'job_id' => $this->jobId,
                'original_error' => $exception->getMessage(),
                'notification_error' => $notificationException->getMessage()
            ]);
        }
    }

    /**
     * ジョブ失敗時の処理
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('Slack sync job failed permanently', [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'total_attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        // 進捗状況を最終失敗状態に更新
        $progressKey = "slack_sync_progress:{$this->user->id}:{$this->jobId}";
        $currentProgress = Cache::get($progressKey, []);
        
        Cache::put($progressKey, array_merge($currentProgress, [
            'status' => 'failed_permanently',
            'failed_permanently_at' => now()->toISOString(),
            'final_error' => $exception->getMessage(),
            'total_attempts' => $this->attempts()
        ]), 86400); // 24時間保持
    }

    /**
     * ジョブの一意キー（重複防止用）
     */
    public function uniqueId(): string
    {
        return "sync_slack_{$this->user->id}_{$this->workspace->id}";
    }

    /**
     * 進捗状況を取得（静的メソッド）
     */
    public static function getProgress(User $user, string $jobId): ?array
    {
        $progressKey = "slack_sync_progress:{$user->id}:{$jobId}";
        return Cache::get($progressKey);
    }

    /**
     * 実行中のジョブを取得（静的メソッド）
     */
    public static function getRunningJobs(User $user): array
    {
        $pattern = "slack_sync_progress:{$user->id}:*";
        
        // Simple implementation for basic cache drivers
        $runningJobs = [];
        
        // Alternative implementation using cache tags or simple key iteration
        for ($i = 0; $i < 100; $i++) { // Check recent jobs
            $testKey = "slack_sync_progress:{$user->id}:sync_" . dechex($i);
            if ($progress = Cache::get($testKey)) {
                if (in_array($progress['status'], ['running', 'pending'])) {
                    $runningJobs[] = $progress;
                }
            }
        }
        
        return $runningJobs;
    }
}