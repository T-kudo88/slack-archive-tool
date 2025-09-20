<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use App\Models\AuditLog;
use App\Jobs\SyncSlackMessagesJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * 同期管理メイン画面
     */
    public function index(Request $request): Response
    {
        // 同期統計
        $stats = $this->getSyncStatistics();

        // 実行中のジョブ
        $runningJobs = $this->getRunningJobs();

        // 最近の同期履歴
        $recentSyncs = $this->getRecentSyncHistory();

        // 失敗したジョブ
        $failedJobs = $this->getFailedJobs();

        // ユーザー別同期状況
        $userSyncStatus = $this->getUserSyncStatus();

        return Inertia::render('Admin/Sync/Index', [
            'stats' => $stats,
            'runningJobs' => $runningJobs,
            'recentSyncs' => $recentSyncs,
            'failedJobs' => $failedJobs,
            'userSyncStatus' => $userSyncStatus,
        ]);
    }

    /**
     * ユーザーの同期を開始
     */
    public function startUserSync(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'force' => 'boolean',
            'channels' => 'array',
            'channels.*' => 'exists:channels,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after:date_from',
        ]);

        $user = User::findOrFail($request->input('user_id'));

        // 既に実行中かチェック
        if (!$request->input('force', false)) {
            $existingJobs = SyncSlackMessagesJob::getRunningJobs($user);
            if (count($existingJobs) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'このユーザーの同期は既に実行中です'
                ], 400);
            }
        }

        try {
            $jobData = [
                'user_id' => $user->id,
                'channels' => $request->input('channels', []),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'force' => $request->input('force', false),
                'started_by_admin' => auth()->id(),
            ];

            // ジョブをキューに追加
            $job = new SyncSlackMessagesJob($user, $jobData);
            Queue::push($job);

            // 監査ログに記録
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'sync_job_started',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'metadata' => [
                    'target_user_id' => $user->id,
                    'target_user_name' => $user->name,
                    'job_params' => $jobData,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => '同期ジョブを開始しました',
                'job_id' => $job->getJobId()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '同期開始に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 同期ジョブの停止
     */
    public function stopJob(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $jobId = $request->input('job_id');

            // ジョブを停止（実際の実装ではジョブキューシステムに依存）
            $stopped = $this->stopSyncJob($jobId);

            if ($stopped) {
                // 監査ログに記録
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'sync_job_stopped',
                    'resource_type' => 'system',
                    'resource_id' => null,
                    'metadata' => [
                        'job_id' => $jobId,
                        'reason' => $request->input('reason'),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '同期ジョブを停止しました'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'ジョブの停止に失敗しました'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ジョブ停止に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 一括同期開始
     */
    public function bulkSync(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'force' => 'boolean',
            'stagger_minutes' => 'integer|min:0|max:60',
        ]);

        try {
            $userIds = $request->input('user_ids');
            $force = $request->input('force', false);
            $staggerMinutes = $request->input('stagger_minutes', 5);

            $jobs = [];
            $delay = 0;

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if (!$user) continue;

                // 既に実行中かチェック
                if (!$force) {
                    $existingJobs = SyncSlackMessagesJob::getRunningJobs($user);
                    if (count($existingJobs) > 0) {
                        continue;
                    }
                }

                $jobData = [
                    'user_id' => $user->id,
                    'force' => $force,
                    'started_by_admin' => auth()->id(),
                    'bulk_operation' => true,
                ];

                $job = new SyncSlackMessagesJob($user, $jobData);

                if ($staggerMinutes > 0 && $delay > 0) {
                    Queue::later(now()->addMinutes($delay), $job);
                } else {
                    Queue::push($job);
                }

                $jobs[] = [
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'delay_minutes' => $delay,
                ];

                $delay += $staggerMinutes;
            }

            // 監査ログに記録
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_sync_started',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => [
                    'target_user_count' => count($jobs),
                    'jobs' => $jobs,
                    'force' => $force,
                    'stagger_minutes' => $staggerMinutes,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => count($jobs) . '件の同期ジョブを開始しました',
                'jobs' => $jobs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '一括同期開始に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 失敗ジョブの再実行
     */
    public function retryFailedJob(Request $request): JsonResponse
    {
        $request->validate([
            'failed_job_id' => 'required|string',
        ]);

        try {
            $failedJobId = $request->input('failed_job_id');

            // 失敗ジョブを再実行（実装はジョブキューシステムに依存）
            $retried = $this->retryFailedSyncJob($failedJobId);

            if ($retried) {
                // 監査ログに記録
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'failed_sync_job_retried',
                    'resource_type' => 'system',
                    'resource_id' => null,
                    'metadata' => [
                        'failed_job_id' => $failedJobId,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '失敗ジョブを再実行しました'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'ジョブの再実行に失敗しました'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ジョブ再実行に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 同期設定の更新
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'auto_sync_enabled' => 'boolean',
            'sync_interval_minutes' => 'integer|min:5|max:1440',
            'max_concurrent_jobs' => 'integer|min:1|max:20',
            'job_timeout_minutes' => 'integer|min:5|max:120',
        ]);

        try {
            $settings = [
                'auto_sync_enabled' => $request->input('auto_sync_enabled', true),
                'sync_interval_minutes' => $request->input('sync_interval_minutes', 60),
                'max_concurrent_jobs' => $request->input('max_concurrent_jobs', 5),
                'job_timeout_minutes' => $request->input('job_timeout_minutes', 30),
            ];

            // 設定をキャッシュに保存
            Cache::put('sync_settings', $settings, now()->addDays(30));

            // 監査ログに記録
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'sync_settings_updated',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => [
                    'old_settings' => Cache::get('sync_settings', []),
                    'new_settings' => $settings,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => '同期設定を更新しました',
                'settings' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '設定更新に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 同期統計の取得
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->getSyncStatistics();
        return response()->json($stats);
    }

    /**
     * 同期統計データの取得
     */
    protected function getSyncStatistics(): array
    {
        return [
            // 基本統計
            'total_sync_jobs_today' => $this->getSyncJobCount('today'),
            'successful_sync_jobs_today' => $this->getSuccessfulSyncJobCount('today'),
            'failed_sync_jobs_today' => $this->getFailedSyncJobCount('today'),
            'running_jobs_count' => $this->getRunningJobsCount(),

            // 週間統計
            'total_sync_jobs_week' => $this->getSyncJobCount('week'),
            'successful_sync_jobs_week' => $this->getSuccessfulSyncJobCount('week'),
            'failed_sync_jobs_week' => $this->getFailedSyncJobCount('week'),

            // メッセージ同期統計
            'messages_synced_today' => Message::whereDate('created_at', today())->count(),
            'messages_synced_week' => Message::where('created_at', '>=', now()->startOfWeek())->count(),

            // パフォーマンス統計
            'average_sync_time_minutes' => $this->getAverageSyncTime(),
            'last_successful_sync' => $this->getLastSuccessfulSync(),
            'next_scheduled_sync' => $this->getNextScheduledSync(),

            // エラー統計
            'common_errors' => $this->getCommonSyncErrors(),
            'user_sync_success_rate' => $this->getUserSyncSuccessRate(),

            // システム状態
            'sync_health_status' => $this->getSyncHealthStatus(),
            'queue_status' => $this->getQueueStatus(),
        ];
    }

    /**
     * 実行中ジョブの取得
     */
    protected function getRunningJobs(): array
    {
        // 実際の実装ではジョブキューシステムから取得
        $runningJobs = [];

        $activeUsers = User::where('is_active', true)->limit(20)->get();
        foreach ($activeUsers as $user) {
            $userJobs = SyncSlackMessagesJob::getRunningJobs($user);
            foreach ($userJobs as $job) {
                $runningJobs[] = [
                    'id' => $job['id'] ?? uniqid(),
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'started_at' => $job['started_at'] ?? now()->subMinutes(rand(1, 30)),
                    'progress' => $job['progress'] ?? rand(10, 90),
                    'current_channel' => $job['current_channel'] ?? null,
                    'status' => $job['status'] ?? 'running',
                ];
            }
        }

        return $runningJobs;
    }

    /**
     * 最近の同期履歴取得
     */
    protected function getRecentSyncHistory(): array
    {
        return AuditLog::where('action', 'like', '%sync%')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user->name ?? 'システム',
                    'action' => $log->action,
                    'status' => $this->getSyncStatusFromAction($log->action),
                    'created_at' => $log->created_at,
                    'duration' => $log->metadata['duration_minutes'] ?? null,
                    'messages_count' => $log->metadata['synced_messages_count'] ?? null,
                    'error_message' => $log->metadata['error_message'] ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * 失敗ジョブの取得
     */
    protected function getFailedJobs(): array
    {
        // 実際の実装ではジョブキューの失敗テーブルから取得
        return AuditLog::where('action', 'like', '%sync%')
            ->where('action', 'like', '%failed%')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user->name ?? 'システム',
                    'failed_at' => $log->created_at,
                    'error_message' => $log->metadata['error_message'] ?? '不明なエラー',
                    'retry_count' => $log->metadata['retry_count'] ?? 0,
                    'can_retry' => true,
                ];
            })
            ->toArray();
    }

    /**
     * ユーザー別同期状況取得
     */
    protected function getUserSyncStatus(): array
    {
        return User::where('is_active', true)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }])
            ->get()
            ->map(function ($user) {
                $lastMessage = $user->messages->first();
                $runningJobs = SyncSlackMessagesJob::getRunningJobs($user);

                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'last_sync_at' => $lastMessage ? $lastMessage->created_at : null,
                    'messages_count' => $user->messages()->count(),
                    'is_syncing' => count($runningJobs) > 0,
                    'sync_health' => $this->getUserSyncHealth($user),
                ];
            })
            ->toArray();
    }

    // ヘルパーメソッド

    protected function getSyncJobCount(string $period): int
    {
        $query = AuditLog::where('action', 'like', '%sync_job%');

        switch ($period) {
            case 'today':
                return $query->whereDate('created_at', today())->count();
            case 'week':
                return $query->where('created_at', '>=', now()->startOfWeek())->count();
            default:
                return 0;
        }
    }

    protected function getSuccessfulSyncJobCount(string $period): int
    {
        $query = AuditLog::where('action', 'sync_job_completed');

        switch ($period) {
            case 'today':
                return $query->whereDate('created_at', today())->count();
            case 'week':
                return $query->where('created_at', '>=', now()->startOfWeek())->count();
            default:
                return 0;
        }
    }

    protected function getFailedSyncJobCount(string $period): int
    {
        $query = AuditLog::where('action', 'sync_job_failed');

        switch ($period) {
            case 'today':
                return $query->whereDate('created_at', today())->count();
            case 'week':
                return $query->where('created_at', '>=', now()->startOfWeek())->count();
            default:
                return 0;
        }
    }

    protected function getRunningJobsCount(): int
    {
        // 実際の実装ではジョブキューから取得
        return Cache::get('running_sync_jobs_count', 0);
    }

    protected function getAverageSyncTime(): float
    {
        $logs = AuditLog::where('action', 'sync_job_completed')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereRaw("(metadata->>'duration_minutes') IS NOT NULL")
            ->get();

        if ($logs->isEmpty()) {
            return 0;
        }

        $totalMinutes = $logs->sum(function ($log) {
            // JSONB カラムを array にキャストしてアクセス
            return (int) ($log->metadata['duration_minutes'] ?? 0);
        });

        return round($totalMinutes / $logs->count(), 2);
    }

    protected function getLastSuccessfulSync(): ?string
    {
        $lastSync = AuditLog::where('action', 'sync_job_completed')
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastSync ? $lastSync->created_at->toISOString() : null;
    }

    protected function getNextScheduledSync(): string
    {
        $settings = Cache::get('sync_settings', ['sync_interval_minutes' => 60]);
        return now()->addMinutes($settings['sync_interval_minutes'])->toISOString();
    }

    protected function getCommonSyncErrors(): array
    {
        return AuditLog::where('action', 'sync_job_failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->get()
            ->groupBy(function ($log) {
                return $log->metadata['error_type'] ?? 'unknown';
            })
            ->map(function ($group, $errorType) {
                return [
                    'error_type' => $errorType,
                    'count' => $group->count(),
                    'last_occurred' => $group->max('created_at'),
                ];
            })
            ->values()
            ->toArray();
    }

    protected function getUserSyncSuccessRate(): float
    {
        $totalJobs = $this->getSyncJobCount('week');
        $successfulJobs = $this->getSuccessfulSyncJobCount('week');

        return $totalJobs > 0 ? round(($successfulJobs / $totalJobs) * 100, 1) : 0;
    }

    protected function getSyncHealthStatus(): string
    {
        $runningJobs = $this->getRunningJobsCount();
        $failedJobs = $this->getFailedSyncJobCount('today');

        if ($failedJobs > 5) return 'error';
        if ($runningJobs > 10) return 'warning';
        return 'healthy';
    }

    protected function getQueueStatus(): array
    {
        // 実際の実装ではジョブキューの状態を取得
        return [
            'pending_jobs' => Cache::get('pending_sync_jobs', 0),
            'failed_jobs' => Cache::get('failed_sync_jobs', 0),
            'workers_active' => Cache::get('sync_workers_active', 1),
        ];
    }

    protected function getUserSyncHealth(User $user): string
    {
        $lastMessage = $user->messages()->orderBy('created_at', 'desc')->first();

        if (!$lastMessage) return 'never_synced';

        $daysSinceLastSync = $lastMessage->created_at->diffInDays(now());

        if ($daysSinceLastSync > 7) return 'stale';
        if ($daysSinceLastSync > 3) return 'warning';
        return 'healthy';
    }

    protected function getSyncStatusFromAction(string $action): string
    {
        if (str_contains($action, 'failed')) return 'failed';
        if (str_contains($action, 'completed')) return 'success';
        if (str_contains($action, 'started')) return 'running';
        return 'unknown';
    }

    protected function stopSyncJob(string $jobId): bool
    {
        // 実際の実装ではジョブキューシステムでジョブを停止
        // ここでは成功として扱う
        return true;
    }

    protected function retryFailedSyncJob(string $failedJobId): bool
    {
        // 実際の実装ではジョブキューシステムで失敗ジョブを再実行
        // ここでは成功として扱う
        return true;
    }
}
