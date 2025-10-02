<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
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
        // 管理者ユーザーだけはリレーションで引く（監査主体が誰か）
        $query = AuditLog::with('adminUser');

        // ── フィルタリング ─────────────────────────────
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('resource_type', 'like', "%{$search}%")
                    ->orWhereHas('adminUser', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    // 対象ユーザーは metadata に格納しているので JSONB を直接検索
                    ->orWhereRaw("(metadata->>'target_user_name') ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("(metadata->>'target_user_email') ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("(metadata->>'ip_address') ILIKE ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }

        if ($request->filled('user_id')) {
            // 管理者ID または 対象ユーザーID（metadata）
            $uid = $request->input('user_id');
            $query->where(function ($q) use ($uid) {
                $q->where('admin_user_id', $uid)
                    ->orWhereRaw("(metadata->>'target_user_id') = ?", [$uid]);
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
                    ->orWhere('action', 'like', 'bulk_%');
            });
        }

        // ── ソート ─────────────────────────────────────
        $sortBy    = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowed   = ['created_at', 'action', 'resource_type', 'admin_user_id'];
        if (in_array($sortBy, $allowed, true)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // ── ページネーション ───────────────────────────
        $perPage = (int) $request->input('per_page', 50);
        $logs    = $query->paginate($perPage)->appends($request->query());

        // ── 互換層：フロントが期待する形に整形（metadata を必ず {} に） ──
        $logs->getCollection()->transform(function (AuditLog $log) {
            $meta = is_array($log->metadata) ? $log->metadata : [];

            return [
                'id'   => $log->id,
                'action' => $log->action,
                'resource_type' => $log->resource_type,
                'created_at' => $log->created_at,

                // 監査主体（管理者）
                'admin_user' => [
                    'id'    => $log->adminUser->id   ?? null,
                    'name'  => $log->adminUser->name ?? '不明',
                    'email' => $log->adminUser->email ?? null,
                ],

                // 対象ユーザー（旧 accessedUser 互換）
                'accessed_user' => [
                    'id'    => $meta['target_user_id']    ?? null,
                    'name'  => $meta['target_user_name']  ?? null,
                    'email' => $meta['target_user_email'] ?? null,
                ],

                // フロントで Object.entries() しても落ちないように常にオブジェクト化
                'metadata' => (object) $meta,
            ];
        });

        // 簡易統計・フィルタ選択肢
        $stats         = $this->getAuditStats($request);
        $filterOptions = $this->getFilterOptions();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs'          => $logs,
            'filters'       => $request->only([
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
            'stats'         => $stats,
            'filterOptions' => $filterOptions,
        ]);
    }

    /**
     * 監査ログ詳細
     */
    public function show(AuditLog $auditLog): Response
    {
        $auditLog->load('adminUser');
        $meta = is_array($auditLog->metadata) ? $auditLog->metadata : [];

        // 関連ログ（簡易）
        $relatedLogs = AuditLog::where('id', '!=', $auditLog->id)
            ->where(function ($q) use ($auditLog, $meta) {
                $q->where(function ($q2) use ($auditLog) {
                    $q2->where('resource_type', $auditLog->resource_type)
                        ->where('resource_id',  $auditLog->resource_id);
                })
                    ->orWhere(function ($q3) use ($meta) {
                        if (!empty($meta['session_id'])) {
                            $q3->whereRaw("metadata->>'session_id' = ?", [$meta['session_id']]);
                        }
                    });
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function (AuditLog $log) {
                $m = is_array($log->metadata) ? $log->metadata : [];
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'created_at' => $log->created_at,
                    'admin_user_name' => optional($log->adminUser)->name,
                    'accessed_user_name' => $m['target_user_name'] ?? null,
                ];
            });

        return Inertia::render('Admin/AuditLogs/Show', [
            'log' => [
                'id' => $auditLog->id,
                'action' => $auditLog->action,
                'resource_type' => $auditLog->resource_type,
                'created_at' => $auditLog->created_at,
                'admin_user' => [
                    'id'    => $auditLog->adminUser->id   ?? null,
                    'name'  => $auditLog->adminUser->name ?? '不明',
                    'email' => $auditLog->adminUser->email ?? null,
                ],
                'accessed_user' => [
                    'id'    => $meta['target_user_id']    ?? null,
                    'name'  => $meta['target_user_name']  ?? null,
                    'email' => $meta['target_user_email'] ?? null,
                ],
                'metadata' => (object) $meta,
            ],
            'relatedLogs' => $relatedLogs,
            'locationInfo' => $this->getLocationInfo($meta['ip_address'] ?? null),
        ]);
    }

    /**
     * 簡易統計
     */
    protected function getAuditStats(Request $request): array
    {
        $days = (int) $request->input('days', 30);

        $totalLogs = AuditLog::where('created_at', '>=', now()->subDays($days))->count();
        $uniqueAdmins = AuditLog::where('created_at', '>=', now()->subDays($days))
            ->distinct('admin_user_id')->count('admin_user_id');

        // JSONB の distinct は DB 生 SQL で数える
        $uniqueTargets = DB::table('audit_logs')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereRaw("(metadata->>'target_user_id') IS NOT NULL")
            ->distinct()
            ->count(DB::raw("(metadata->>'target_user_id')"));

        return [
            'total_logs'     => $totalLogs,
            'unique_admins'  => $uniqueAdmins,
            'unique_targets' => $uniqueTargets,
        ];
    }

    /**
     * フィルタ用オプション
     */
    protected function getFilterOptions(): array
    {
        $actions = AuditLog::select('action')->distinct()->pluck('action')->toArray();
        $resourceTypes = AuditLog::select('resource_type')->distinct()->pluck('resource_type')->toArray();

        // 管理者の候補
        $adminUsers = User::select('id', 'name', 'email')
            ->whereIn('id', AuditLog::select('admin_user_id')->distinct())
            ->orderBy('name')
            ->get()
            ->toArray();

        // 対象ユーザー候補（metadata から）
        $targetUsers = DB::table('audit_logs')
            ->selectRaw("(metadata->>'target_user_id')  AS id,
                         (metadata->>'target_user_name')  AS name,
                         (metadata->>'target_user_email') AS email")
            ->whereRaw("(metadata->>'target_user_id') IS NOT NULL")
            ->distinct()
            ->orderBy('name')
            ->get()
            ->toArray();

        return [
            'actions'        => $actions,
            'resource_types' => $resourceTypes,
            'admin_users'    => $adminUsers,
            'target_users'   => $targetUsers,
        ];
    }

    protected function getLocationInfo(?string $ip): array
    {
        if (!$ip) return [];
        return ['ip' => $ip, 'location' => 'N/A'];
    }
}
