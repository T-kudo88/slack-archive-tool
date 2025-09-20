<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * 監査ログメイン画面
     */
    public function index(Request $request): Response
    {
        $query = AuditLog::with(['adminUser', 'accessedUser']);

        // フィルタリング
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('resource_type', 'like', "%{$search}%")
                    ->orWhereHas('adminUser', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('accessedUser', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereRaw("(metadata->>'ip_address') LIKE ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }

        if ($request->filled('user_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('admin_user_id', $request->input('user_id'))
                    ->orWhere('accessed_user_id', $request->input('user_id'));
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        if ($request->filled('ip_address')) {
            $query->whereRaw("(metadata->>'ip_address') = ?", [$request->input('ip_address')]);
        }

        if ($request->filled('suspicious')) {
            $query->where(function ($q) {
                $q->whereRaw("(metadata->>'is_unusual')::boolean = true")
                    ->orWhere('action', 'like', '%failed%')
                    ->orWhere('action', 'like', '%suspicious%')
                    ->orWhere('action', 'bulk_%');
            });
        }

        // ソート
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['created_at', 'action', 'resource_type', 'admin_user_id', 'accessed_user_id'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // ページネーション
        $perPage = $request->input('per_page', 50);
        $logs = $query->paginate($perPage)->appends($request->query());

        // 簡易統計情報
        $stats = $this->getAuditStats($request);

        // フィルタオプション
        $filterOptions = $this->getFilterOptions();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only([
                'search',
                'action',
                'resource_type',
                'user_id',
                'date_from',
                'date_to',
                'ip_address',
                'suspicious',
                'sort_by',
                'sort_order',
                'per_page'
            ]),
            'stats' => $stats,
            'filterOptions' => $filterOptions,
        ]);
    }

    /**
     * 監査ログ詳細
     */
    public function show(AuditLog $auditLog): Response
    {
        $auditLog->load(['adminUser', 'accessedUser']);

        // 関連するログ
        $relatedLogs = AuditLog::where('id', '!=', $auditLog->id)
            ->where(function ($query) use ($auditLog) {
                $query->where(function ($q) use ($auditLog) {
                    $q->where('resource_type', $auditLog->resource_type)
                        ->where('resource_id', $auditLog->resource_id);
                })->orWhere(function ($q) use ($auditLog) {
                    if (isset($auditLog->metadata['session_id'])) {
                        $q->whereRaw("metadata->>'session_id' = ?", [$auditLog->metadata['session_id']]);
                    }
                })->orWhere(function ($q) use ($auditLog) {
                    $q->where(function ($q2) use ($auditLog) {
                        $q2->where('admin_user_id', $auditLog->admin_user_id)
                            ->orWhere('accessed_user_id', $auditLog->accessed_user_id);
                    })->whereBetween('created_at', [
                        $auditLog->created_at->subMinutes(30),
                        $auditLog->created_at->addMinutes(30)
                    ]);
                });
            })
            ->with(['adminUser', 'accessedUser'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $locationInfo = $this->getLocationInfo($auditLog->metadata['ip_address'] ?? null);

        return Inertia::render('Admin/AuditLogs/Show', [
            'log' => $auditLog,
            'relatedLogs' => $relatedLogs,
            'locationInfo' => $locationInfo,
        ]);
    }

    /**
     * 簡易統計情報の取得
     */
    protected function getAuditStats(Request $request): array
    {
        $days = $request->input('days', 30);

        return [
            'total_logs' => AuditLog::where('created_at', '>=', now()->subDays($days))->count(),
            'unique_admins' => AuditLog::where('created_at', '>=', now()->subDays($days))
                ->distinct('admin_user_id')->count('admin_user_id'),
            'unique_accessed' => AuditLog::where('created_at', '>=', now()->subDays($days))
                ->distinct('accessed_user_id')->count('accessed_user_id'),
        ];
    }

    /**
     * フィルタオプションの取得
     */
    protected function getFilterOptions(): array
    {
        return [
            'actions' => AuditLog::select('action')->distinct()->pluck('action')->toArray(),
            'resource_types' => AuditLog::select('resource_type')->distinct()->pluck('resource_type')->toArray(),
            'users' => User::select('id', 'name', 'email')
                ->whereIn('id', AuditLog::select('admin_user_id')->distinct())
                ->orWhereIn('id', AuditLog::select('accessed_user_id')->distinct())
                ->orderBy('name')
                ->get()
                ->toArray(),
        ];
    }
}
