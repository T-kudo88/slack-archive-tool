<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * 管理者ダッシュボードメイン画面
     */
    public function index(Request $request): Response
    {
        $stats = $this->getSystemStats();
        $recentActivities = $this->getRecentActivities();
        $securityAlerts = $this->getSecurityAlerts();
        $syncStatus = $this->getSyncStatus();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
            'securityAlerts' => $securityAlerts,
            'syncStatus' => $syncStatus,
        ]);
    }

    /**
     * システム統計データの取得
     */
    protected function getSystemStats(): array
    {
        return Cache::remember('admin_dashboard_stats', 300, function () {
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $adminUsers = User::where('is_admin', true)->count();

            $totalWorkspaces = Workspace::count();
            $totalChannels = Channel::count();
            $publicChannels = Channel::where('is_private', false)->where('is_dm', false)->count();
            $privateChannels = Channel::where('is_private', true)->where('is_dm', false)->count();
            $dmChannels = Channel::where('is_dm', true)->count();

            $totalMessages = Message::count();
            $todayMessages = Message::whereDate('created_at', today())->count();
            $thisWeekMessages = Message::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count();
            $thisMonthMessages = Message::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count();

            $messagesWithFiles = Message::where('has_files', true)->count();

            // 最近の同期アクティビティ
            $recentSyncJobs = Cache::tags(['slack_sync'])->get('recent_sync_stats', [
                'completed_today' => 0,
                'failed_today' => 0,
                'in_progress' => 0
            ]);

            // ストレージ使用量（概算）
            $storageStats = [
                'messages_size_mb' => round($totalMessages * 0.5, 2), // 概算
                'files_size_mb' => round($messagesWithFiles * 2.5, 2), // 概算
            ];

            // ユーザーアクティビティ統計
            $userStats = [
                'most_active_users' => User::withCount('messages')
                    ->orderBy('messages_count', 'desc')
                    ->limit(5)
                    ->get(['id', 'name', 'display_name', 'messages_count']),
                'recent_logins' => User::whereNotNull('last_login_at')
                    ->where('last_login_at', '>=', now()->subDays(7))
                    ->count()
            ];

            return [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'inactive' => $totalUsers - $activeUsers,
                    'admins' => $adminUsers,
                    'recent_logins' => $userStats['recent_logins'],
                    'most_active' => $userStats['most_active_users']
                ],
                'workspaces' => [
                    'total' => $totalWorkspaces
                ],
                'channels' => [
                    'total' => $totalChannels,
                    'public' => $publicChannels,
                    'private' => $privateChannels,
                    'dm' => $dmChannels
                ],
                'messages' => [
                    'total' => $totalMessages,
                    'today' => $todayMessages,
                    'this_week' => $thisWeekMessages,
                    'this_month' => $thisMonthMessages,
                    'with_files' => $messagesWithFiles,
                    'average_per_day' => $totalMessages > 0 ? round($totalMessages / max(1, now()->diffInDays(Message::min('created_at') ?? now())), 2) : 0
                ],
                'sync' => $recentSyncJobs,
                'storage' => $storageStats,
                'growth' => $this->getGrowthStats()
            ];
        });
    }

    /**
     * 成長統計の取得
     */
    /**
     * 成長統計の取得
     */
    protected function getGrowthStats(): array
    {
        // 過去30日間の日別統計
        $dailyStats = DB::table('messages')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // 過去12ヶ月の月別統計（PostgreSQL 用）
        $monthlyStats = DB::table('messages')
            ->select(DB::raw("to_char(created_at, 'YYYY-MM') as month"), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        return [
            'daily' => $dailyStats,
            'monthly' => $monthlyStats
        ];
    }

    /**
     * 最近のアクティビティ取得
     */
    protected function getRecentActivities(): array
    {
        $activities = collect();

        // 最近のユーザー登録
        $recentUsers = User::where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(function ($user) {
                return [
                    'type' => 'user_registered',
                    'title' => '新規ユーザー登録',
                    'description' => $user->name . ' (' . $user->email . ')',
                    'timestamp' => $user->created_at,
                    'icon' => 'user-plus',
                    'color' => 'green'
                ];
            });

        // 最近のメッセージ（大量の場合は制限）
        $recentMessages = Message::with(['user', 'channel'])
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($message) {
                return [
                    'type' => 'message_created',
                    'title' => '新しいメッセージ',
                    'description' => $message->user->name . ' が #' . $message->channel->name . ' に投稿',
                    'timestamp' => $message->created_at,
                    'icon' => 'chat',
                    'color' => 'blue'
                ];
            });

        $activities = $activities->merge($recentUsers)->merge($recentMessages);

        return $activities->sortByDesc('timestamp')->take(20)->values()->toArray();
    }

    /**
     * セキュリティアラート取得
     */
    protected function getSecurityAlerts(): array
    {
        $alerts = collect();

        // 管理者アクセスの監査ログから異常検知
        $suspiciousAdminAccess = AuditLog::where('action', 'admin_access')
            ->where('created_at', '>=', now()->subHours(24))
            ->whereJsonContains('metadata->is_unusual', true)
            ->count();

        if ($suspiciousAdminAccess > 0) {
            $alerts->push([
                'id' => 'suspicious_admin_access',
                'type' => 'warning',
                'title' => '異常な管理者アクセス',
                'description' => "過去24時間で{$suspiciousAdminAccess}件の異常な管理者アクセスを検出",
                'severity' => 'medium',
                'timestamp' => now(),
                'action_url' => '/admin/audit-logs?filter=suspicious'
            ]);
        }

        // 大量のデータアクセス
        $bulkDataAccess = AuditLog::where('action', 'bulk_data_access')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($bulkDataAccess > 10) {
            $alerts->push([
                'id' => 'bulk_data_access',
                'type' => 'info',
                'title' => '大量データアクセス',
                'description' => "過去24時間で{$bulkDataAccess}件の大量データアクセスを検出",
                'severity' => 'low',
                'timestamp' => now(),
                'action_url' => '/admin/audit-logs?action=bulk_data_access'
            ]);
        }

        // 失敗した同期ジョブ
        $failedSyncJobs = Cache::get('failed_sync_jobs_count', 0);
        if ($failedSyncJobs > 0) {
            $alerts->push([
                'id' => 'failed_sync_jobs',
                'type' => 'error',
                'title' => '同期ジョブ失敗',
                'description' => "{$failedSyncJobs}個の同期ジョブが失敗しています",
                'severity' => 'high',
                'timestamp' => now(),
                'action_url' => '/admin/sync-status'
            ]);
        }

        // 非アクティブ管理者
        $inactiveAdmins = User::where('is_admin', true)
            ->where('is_active', false)
            ->count();

        if ($inactiveAdmins > 0) {
            $alerts->push([
                'id' => 'inactive_admins',
                'type' => 'warning',
                'title' => '非アクティブ管理者',
                'description' => "{$inactiveAdmins}人の管理者が非アクティブです",
                'severity' => 'medium',
                'timestamp' => now(),
                'action_url' => '/admin/users?filter=inactive_admins'
            ]);
        }

        return $alerts->sortByDesc('severity')->values()->toArray();
    }

    /**
     * 同期ステータス取得
     */
    protected function getSyncStatus(): array
    {
        // 実行中のジョブをチェック（簡略版）
        $runningJobs = 0;
        $users = User::where('is_active', true)->limit(10)->get();

        foreach ($users as $user) {
            $runningJobs += count(\App\Jobs\SyncSlackMessagesJob::getRunningJobs($user));
        }

        return [
            'running_jobs' => $runningJobs,
            'last_sync' => Cache::get('last_successful_sync', null),
            'next_scheduled_sync' => now()->addHour()->format('Y-m-d H:00:00'),
            'total_synced_messages_today' => Message::whereDate('created_at', today())->count(),
            'sync_health' => $runningJobs === 0 ? 'healthy' : 'syncing'
        ];
    }

    /**
     * リアルタイム統計API
     */
    public function realtimeStats(Request $request): JsonResponse
    {
        $type = $request->input('type', 'overview');

        switch ($type) {
            case 'messages':
                return response()->json([
                    'total' => Message::count(),
                    'today' => Message::whereDate('created_at', today())->count(),
                    'last_hour' => Message::where('created_at', '>=', now()->subHour())->count(),
                ]);

            case 'users':
                return response()->json([
                    'total' => User::count(),
                    'active' => User::where('is_active', true)->count(),
                    'online' => User::where('last_login_at', '>=', now()->subMinutes(15))->count(),
                ]);

            case 'sync':
                return response()->json($this->getSyncStatus());

            default:
                return response()->json($this->getSystemStats());
        }
    }

    /**
     * システムヘルスチェック
     */
    public function healthCheck(): JsonResponse
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
            'queue' => $this->checkQueueHealth(),
        ];

        $overallStatus = collect($health)->every(fn($check) => $check['status'] === 'healthy')
            ? 'healthy'
            : 'warning';

        return response()->json([
            'overall_status' => $overallStatus,
            'checks' => $health,
            'timestamp' => now()->toISOString()
        ]);
    }

    protected function checkDatabaseHealth(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    protected function checkCacheHealth(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $result = Cache::get('health_check');
            return $result === 'ok'
                ? ['status' => 'healthy', 'message' => 'Cache working normally']
                : ['status' => 'warning', 'message' => 'Cache not working properly'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }

    protected function checkStorageHealth(): array
    {
        try {
            $diskUsage = disk_free_space('/') / disk_total_space('/');
            if ($diskUsage > 0.9) {
                return ['status' => 'warning', 'message' => 'Disk usage is high (>90%)'];
            }
            return ['status' => 'healthy', 'message' => 'Storage healthy'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }

    protected function checkQueueHealth(): array
    {
        // 簡単なキュー健全性チェック
        try {
            // 実際の実装では、失敗ジョブ数やキュー滞留をチェック
            return ['status' => 'healthy', 'message' => 'Queue system operational'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Queue check failed: ' . $e->getMessage()];
        }
    }

    /**
     * システムメンテナンスモード切替
     */
    public function toggleMaintenance(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'message' => 'nullable|string|max:255'
        ]);

        try {
            if ($request->input('enabled')) {
                \Illuminate\Support\Facades\Artisan::call('down', [
                    '--message' => $request->input('message', 'System maintenance in progress')
                ]);
                $status = 'enabled';
            } else {
                \Illuminate\Support\Facades\Artisan::call('up');
                $status = 'disabled';
            }

            // 監査ログに記録
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'maintenance_mode_toggle',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => [
                    'enabled' => $request->input('enabled'),
                    'message' => $request->input('message'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'status' => $status,
                'message' => "Maintenance mode {$status} successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle maintenance mode: ' . $e->getMessage()
            ], 500);
        }
    }
}
