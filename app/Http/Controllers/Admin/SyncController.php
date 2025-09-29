<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Message;
use App\Models\AuditLog;
use App\Jobs\SyncSlackMessagesJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Sync/Index', [
            'stats' => $this->getSyncStatistics(),
            'runningJobs' => $this->getRunningJobs(),
            'recentSyncs' => $this->getRecentSyncHistory(),
            'failedJobs' => $this->getFailedJobs(),
            'userSyncStatus' => $this->getUserSyncStatus(),
        ]);
    }

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

        try {
            $jobData = [
                'user_id' => $user->id,
                'channels' => $request->input('channels', []),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'force' => $request->input('force', false),
                'started_by_admin' => auth()->id(),
            ];

            $job = new SyncSlackMessagesJob($user, $jobData);
            Queue::push($job);

            AuditLog::create([
                'admin_user_id' => auth()->id(),
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

            return response()->json(['success' => true, 'message' => '同期ジョブを開始しました']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '同期開始に失敗: ' . $e->getMessage()], 500);
        }
    }

    public function stopJob(Request $request): JsonResponse
    {
        $request->validate([
            'job_id' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            AuditLog::create([
                'admin_user_id' => auth()->id(),
                'action' => 'sync_job_stopped',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => [
                    'job_id' => $request->input('job_id'),
                    'reason' => $request->input('reason'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json(['success' => true, 'message' => '同期ジョブを停止しました']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'ジョブ停止に失敗: ' . $e->getMessage()], 500);
        }
    }

    public function bulkSync(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'force' => 'boolean',
        ]);

        try {
            $jobs = [];

            foreach ($request->input('user_ids') as $userId) {
                $user = User::find($userId);
                if (!$user) continue;

                $job = new SyncSlackMessagesJob($user, ['started_by_admin' => auth()->id()]);
                Queue::push($job);

                $jobs[] = ['user_id' => $userId, 'user_name' => $user->name];
            }

            AuditLog::create([
                'admin_user_id' => auth()->id(),
                'action' => 'bulk_sync_started',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => [
                    'jobs' => $jobs,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json(['success' => true, 'message' => count($jobs) . '件の同期ジョブを開始しました']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '一括同期失敗: ' . $e->getMessage()], 500);
        }
    }

    public function syncMessages(Request $request): JsonResponse
    {
        try {
            \Artisan::call('slack:sync');
            return response()->json(['success' => true, 'message' => '差分同期が完了しました ✅']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncAllMessages(Request $request): JsonResponse
    {
        try {
            // \Artisan::queue('slack:sync-all');
            \Artisan::call('slack:sync-all'); // すぐ実行したい場合はこちらに変更

            AuditLog::create([
                'admin_user_id' => auth()->id(),
                'action' => 'all_messages_sync_started',
                'resource_type' => 'system',
                'resource_id' => 0, // system全体なので 0 や 1 を固定で入れる
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => '全メッセージ同期が完了しました ✅'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '全メッセージ同期に失敗: ' . $e->getMessage()
            ], 500);
        }
    }

    public function retryFailedJob(Request $request): JsonResponse
    {
        try {
            AuditLog::create([
                'admin_user_id' => auth()->id(),
                'action' => 'failed_sync_job_retried',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => [
                    'failed_job_id' => $request->input('failed_job_id'),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json(['success' => true, 'message' => '失敗ジョブを再実行しました']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '再実行失敗: ' . $e->getMessage()], 500);
        }
    }

    public function updateSettings(Request $request): JsonResponse
    {
        try {
            AuditLog::create([
                'admin_user_id' => auth()->id(),
                'action' => 'sync_settings_updated',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => [
                    'settings' => $request->all(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            return response()->json(['success' => true, 'message' => '同期設定を更新しました']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '設定更新失敗: ' . $e->getMessage()], 500);
        }
    }

    // ここから下は統計取得系のメソッド（省略）
    // getSyncStatistics(), getRunningJobs(), getRecentSyncHistory() などはそのままでOK
}
