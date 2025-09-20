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
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * ユーザー管理メイン画面
     */
    public function index(Request $request): Response
    {
        $query = User::query();

        // フィルタリング
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            switch ($status) {
                case 'active':
                    $query->where('is_active', true);
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'admin':
                    $query->where('is_admin', true);
                    break;
                case 'inactive_admins':
                    $query->where('is_admin', true)->where('is_active', false);
                    break;
            }
        }

        if ($request->filled('workspace_id')) {
            $workspaceId = $request->input('workspace_id');
            $query->whereHas('workspaces', function ($q) use ($workspaceId) {
                $q->where('workspaces.id', $workspaceId);
            });
        }

        // ソート
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['created_at', 'name', 'email', 'last_login_at', 'messages_count'];
        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'messages_count') {
                $query->withCount('messages')->orderBy('messages_count', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        // ページネーション
        $perPage = $request->input('per_page', 25);
        $users = $query->with(['workspaces'])
                      ->withCount(['messages', 'channels'])
                      ->paginate($perPage)
                      ->appends($request->query());

        // 統計情報
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'admin_users' => User::where('is_admin', true)->count(),
            'inactive_admins' => User::where('is_admin', true)->where('is_active', false)->count(),
            'recent_logins' => User::where('last_login_at', '>=', now()->subWeek())->count(),
            'never_logged_in' => User::whereNull('last_login_at')->count(),
        ];

        // フィルタオプション
        $filterOptions = [
            'workspaces' => Workspace::orderBy('name')->get(['id', 'name']),
            'statuses' => [
                ['value' => 'active', 'label' => 'アクティブ'],
                ['value' => 'inactive', 'label' => '非アクティブ'],
                ['value' => 'admin', 'label' => '管理者'],
                ['value' => 'inactive_admins', 'label' => '非アクティブ管理者'],
            ]
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status', 'workspace_id', 'sort_by', 'sort_order', 'per_page']),
            'stats' => $stats,
            'filterOptions' => $filterOptions,
        ]);
    }

    /**
     * ユーザー詳細画面
     */
    public function show(User $user): Response
    {
        $user->load(['workspaces', 'channels']);
        
        // ユーザーの詳細統計
        $stats = [
            'total_messages' => $user->messages()->count(),
            'messages_this_week' => $user->messages()->where('created_at', '>=', now()->startOfWeek())->count(),
            'messages_this_month' => $user->messages()->whereMonth('created_at', now()->month)->count(),
            'channels_count' => $user->channels()->count(),
            'workspaces_count' => $user->workspaces()->count(),
            'files_shared' => $user->messages()->where('has_files', true)->count(),
            'reactions_given' => 0, // 実装時に追加
            'last_activity' => $user->messages()->latest()->first()?->created_at,
        ];

        // 最近のアクティビティ
        $recentMessages = $user->messages()
            ->with(['channel', 'workspace'])
            ->latest()
            ->limit(10)
            ->get();

        // 監査ログ（このユーザーに関する）
        $auditLogs = AuditLog::where('user_id', $user->id)
            ->orWhere('resource_type', 'user')
            ->where('resource_id', $user->id)
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'stats' => $stats,
            'recentMessages' => $recentMessages,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * ユーザーステータス更新（アクティブ/非アクティブ）
     */
    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'is_active' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $user->is_active;
        $newStatus = $request->input('is_active');

        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'ステータスは既に同じ値です'
            ], 400);
        }

        // 自分自身を非アクティブにすることを防ぐ
        if ($user->id === auth()->id() && !$newStatus) {
            return response()->json([
                'success' => false,
                'message' => '自分自身を非アクティブにすることはできません'
            ], 400);
        }

        try {
            DB::transaction(function () use ($user, $newStatus, $request, $oldStatus) {
                $user->update(['is_active' => $newStatus]);

                // 監査ログに記録
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'user_status_changed',
                    'resource_type' => 'user',
                    'resource_id' => $user->id,
                    'metadata' => [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'reason' => $request->input('reason'),
                        'target_user_id' => $user->id,
                        'target_user_name' => $user->name,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'ユーザーステータスを更新しました',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ステータス更新に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 管理者権限更新
     */
    public function updateAdminStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'is_admin' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $user->is_admin;
        $newStatus = $request->input('is_admin');

        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => false,
                'message' => '権限は既に同じ値です'
            ], 400);
        }

        // 自分自身の管理者権限を削除することを防ぐ
        if ($user->id === auth()->id() && !$newStatus) {
            return response()->json([
                'success' => false,
                'message' => '自分自身の管理者権限を削除することはできません'
            ], 400);
        }

        // 最後の管理者を削除することを防ぐ
        if (!$newStatus) {
            $adminCount = User::where('is_admin', true)->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => '最後のアクティブ管理者の権限を削除することはできません'
                ], 400);
            }
        }

        try {
            DB::transaction(function () use ($user, $newStatus, $request, $oldStatus) {
                $user->update(['is_admin' => $newStatus]);

                // 監査ログに記録
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'admin_permission_changed',
                    'resource_type' => 'user',
                    'resource_id' => $user->id,
                    'metadata' => [
                        'old_admin_status' => $oldStatus,
                        'new_admin_status' => $newStatus,
                        'reason' => $request->input('reason'),
                        'target_user_id' => $user->id,
                        'target_user_name' => $user->name,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => '管理者権限を更新しました',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '権限更新に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * パスワードリセット
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($user, $request) {
                $user->update([
                    'password' => Hash::make($request->input('password')),
                    'password_changed_at' => now(),
                ]);

                // 監査ログに記録
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'password_reset_by_admin',
                    'resource_type' => 'user',
                    'resource_id' => $user->id,
                    'metadata' => [
                        'reason' => $request->input('reason'),
                        'target_user_id' => $user->id,
                        'target_user_name' => $user->name,
                        'reset_by_admin' => true,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'パスワードをリセットしました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'パスワードリセットに失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ユーザー削除（論理削除）
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // 自分自身を削除することを防ぐ
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '自分自身を削除することはできません'
            ], 400);
        }

        // 最後の管理者を削除することを防ぐ
        if ($user->is_admin) {
            $adminCount = User::where('is_admin', true)->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => '最後のアクティブ管理者を削除することはできません'
                ], 400);
            }
        }

        try {
            DB::transaction(function () use ($user, $request) {
                // ソフトデリート前の処理
                $user->update([
                    'is_active' => false,
                    'deleted_reason' => $request->input('reason'),
                    'deleted_by' => auth()->id(),
                ]);

                // 監査ログに記録
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'user_deleted',
                    'resource_type' => 'user',
                    'resource_id' => $user->id,
                    'metadata' => [
                        'reason' => $request->input('reason'),
                        'target_user_id' => $user->id,
                        'target_user_name' => $user->name,
                        'target_user_email' => $user->email,
                        'was_admin' => $user->is_admin,
                        'message_count' => $user->messages()->count(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);

                // ソフトデリート実行
                $user->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'ユーザーを削除しました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー削除に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ユーザー復元
     */
    public function restore(Request $request, $userId): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($userId);

        if (!$user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'このユーザーは削除されていません'
            ], 400);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($user, $request) {
                $user->restore();
                $user->update([
                    'is_active' => true,
                    'deleted_reason' => null,
                    'deleted_by' => null,
                ]);

                // 監査ログに記録
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'user_restored',
                    'resource_type' => 'user',
                    'resource_id' => $user->id,
                    'metadata' => [
                        'reason' => $request->input('reason'),
                        'target_user_id' => $user->id,
                        'target_user_name' => $user->name,
                        'restored_by_admin' => true,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'ユーザーを復元しました',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー復元に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 一括操作
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:activate,deactivate,make_admin,remove_admin,delete',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $action = $request->input('action');
        $userIds = $request->input('user_ids');
        $reason = $request->input('reason');

        // 自分自身への操作を防ぐ
        if (in_array(auth()->id(), $userIds)) {
            return response()->json([
                'success' => false,
                'message' => '自分自身に対してこの操作を実行することはできません'
            ], 400);
        }

        try {
            $results = [];
            
            DB::transaction(function () use ($action, $userIds, $reason, $request, &$results) {
                $users = User::whereIn('id', $userIds)->get();
                
                foreach ($users as $user) {
                    switch ($action) {
                        case 'activate':
                            if (!$user->is_active) {
                                $user->update(['is_active' => true]);
                                $results[] = "ユーザー {$user->name} をアクティブにしました";
                            }
                            break;
                        
                        case 'deactivate':
                            if ($user->is_active) {
                                $user->update(['is_active' => false]);
                                $results[] = "ユーザー {$user->name} を非アクティブにしました";
                            }
                            break;
                        
                        case 'make_admin':
                            if (!$user->is_admin) {
                                $user->update(['is_admin' => true]);
                                $results[] = "ユーザー {$user->name} を管理者にしました";
                            }
                            break;
                        
                        case 'remove_admin':
                            if ($user->is_admin) {
                                // 管理者数チェック
                                $adminCount = User::where('is_admin', true)->where('is_active', true)->count();
                                if ($adminCount > count($userIds)) {
                                    $user->update(['is_admin' => false]);
                                    $results[] = "ユーザー {$user->name} の管理者権限を削除しました";
                                }
                            }
                            break;
                        
                        case 'delete':
                            if (!$user->trashed()) {
                                $user->update([
                                    'is_active' => false,
                                    'deleted_reason' => $reason,
                                    'deleted_by' => auth()->id(),
                                ]);
                                $user->delete();
                                $results[] = "ユーザー {$user->name} を削除しました";
                            }
                            break;
                    }

                    // 監査ログに記録
                    AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => "bulk_{$action}",
                        'resource_type' => 'user',
                        'resource_id' => $user->id,
                        'metadata' => [
                            'bulk_operation' => true,
                            'action' => $action,
                            'reason' => $reason,
                            'target_user_id' => $user->id,
                            'target_user_name' => $user->name,
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                        ]
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => '一括操作を完了しました',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '一括操作に失敗しました: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ユーザー統計API
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'admin_users' => User::where('is_admin', true)->count(),
            'users_with_messages' => User::has('messages')->count(),
            'users_by_workspace' => DB::table('user_workspace')
                ->join('workspaces', 'user_workspace.workspace_id', '=', 'workspaces.id')
                ->select('workspaces.name', DB::raw('count(*) as user_count'))
                ->groupBy('workspaces.id', 'workspaces.name')
                ->get(),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->orderBy('date')
                ->get(),
            'login_activity' => [
                'today' => User::where('last_login_at', '>=', now()->startOfDay())->count(),
                'this_week' => User::where('last_login_at', '>=', now()->startOfWeek())->count(),
                'this_month' => User::where('last_login_at', '>=', now()->startOfMonth())->count(),
                'never_logged_in' => User::whereNull('last_login_at')->count(),
            ]
        ];

        return response()->json($stats);
    }
}