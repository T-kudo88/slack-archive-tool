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
use App\Models\AuditLog;

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
    protected ?int $startedByAdminId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        User $user,
        Workspace $workspace,
        ?int $startedByAdminId = null,
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
        $this->startedByAdminId = $startedByAdminId;

        // 個人制限を適用したキューに配置
        $this->onQueue('slack-sync-' . $user->id);
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->user->id),
        ];
    }

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

            $this->initializeProgress();

            $service = new SlackSyncService($this->user, $this->workspace);

            $channels = $this->determineChannelsToSync($service);

            if ($channels->isEmpty()) {
                Log::warning('No accessible channels found for sync', [
                    'job_id' => $this->jobId,
                    'user_id' => $this->user->id
                ]);
                $this->completeJob([], 0);
                return;
            }

            $results = $this->processBatchSync($service, $channels);

            $summary = $this->generateSummary($results, microtime(true) - $startTime);
            $this->completeJob($results, $summary['total_messages']);

            Log::info('Slack sync job completed successfully', array_merge([
                'job_id' => $this->jobId,
                'user_id' => $this->user->id
            ], $summary));
        } catch (Throwable $exception) {
            $this->handleJobException($exception, microtime(true) - $startTime);
            throw $exception;
        }
    }

    private function determineChannelsToSync(SlackSyncService $service): \Illuminate\Database\Eloquent\Collection
    {
        if (!empty($this->channelIds)) {
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

        return $this->getAllAccessibleChannels();
    }

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

    private function processBatchSync(SlackSyncService $service, $channels): array
    {
        $results = [];
        $processed = 0;
        $total = $channels->count();

        foreach ($channels as $channel) {
            try {
                $this->updateProgress($processed, $total, $channel->name);

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
        ], 3600);
    }

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
        ], 7200);

        AuditLog::create([
            'admin_user_id'    => $this->startedByAdminId,
            'accessed_user_id' => $this->user->id,
            'action'           => 'sync_job_completed',
            'resource_type'    => 'workspace',
            'resource_id'      => $this->workspace->id,
            'metadata'         => [
                'job_id'         => $this->jobId,
                'channel_count'  => count($results),
                'messages_count' => $totalMessages,
                'sync_type'      => $this->syncType,
                'full_sync'      => $this->fullSync,
            ]
        ]);
    }

    private function handleJobException(Throwable $exception, float $executionTime): void
    {
        Log::error('Slack sync job failed', [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'execution_time' => round($executionTime, 2),
            'error' => $exception->getMessage()
        ]);

        $progressKey = "slack_sync_progress:{$this->user->id}:{$this->jobId}";
        $currentProgress = Cache::get($progressKey, []);

        Cache::put($progressKey, array_merge($currentProgress, [
            'status' => 'failed',
            'failed_at' => now()->toISOString(),
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts()
        ]), 7200);

        if ($this->attempts() >= $this->tries) {
            $this->notifyAdminOfFailure($exception);
        }

        AuditLog::create([
            'admin_user_id'    => $this->startedByAdminId,
            'accessed_user_id' => $this->user->id,
            'action'           => 'sync_job_failed',
            'resource_type'    => 'workspace',
            'resource_id'      => $this->workspace->id,
            'metadata'         => [
                'job_id'   => $this->jobId,
                'error'    => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'sync_type' => $this->syncType,
                'full_sync' => $this->fullSync,
            ]
        ]);
    }

    private function notifyAdminOfFailure(Throwable $exception): void
    {
        try {
            $admins = User::where('is_admin', true)->where('is_active', true)->get();

            foreach ($admins as $admin) {
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

    public function failed(Throwable $exception): void
    {
        Log::critical('Slack sync job failed permanently', [
            'job_id' => $this->jobId,
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'total_attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        $progressKey = "slack_sync_progress:{$this->user->id}:{$this->jobId}";
        $currentProgress = Cache::get($progressKey, []);

        Cache::put($progressKey, array_merge($currentProgress, [
            'status' => 'failed_permanently',
            'failed_permanently_at' => now()->toISOString(),
            'final_error' => $exception->getMessage(),
            'total_attempts' => $this->attempts()
        ]), 86400);

        AuditLog::create([
            'admin_user_id'    => $this->startedByAdminId,
            'accessed_user_id' => $this->user->id,
            'action'           => 'sync_job_failed',
            'resource_type'    => 'workspace',
            'resource_id'      => $this->workspace->id,
            'metadata'         => [
                'job_id'   => $this->jobId,
                'error'    => $exception->getMessage(),
                'total_attempts' => $this->attempts(),
                'sync_type' => $this->syncType,
                'full_sync' => $this->fullSync,
            ]
        ]);
    }

    public function uniqueId(): string
    {
        return "sync_slack_{$this->user->id}_{$this->workspace->id}";
    }

    public static function getProgress(User $user, string $jobId): ?array
    {
        $progressKey = "slack_sync_progress:{$user->id}:{$jobId}";
        return Cache::get($progressKey);
    }

    public static function getRunningJobs(User $user): array
    {
        $runningJobs = [];

        for ($i = 0; $i < 100; $i++) {
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
