<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;


class MessageController extends Controller
{
    public function __construct()
    {
        // 認証・個人制限は web.php 側のミドルウェアで適用
    }

    /**
     * 個人制限 & DM制限を考慮したメッセージクエリ
     */
    protected function buildAccessibleMessagesQuery(User $user)
    {
        $query = Message::query()
            ->with(['user', 'channel', 'workspace', 'files']);

        if ($user->is_admin) {
            // アドミンは全件OK
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            // 自分が書いたメッセージ
            $q->where('messages.user_id', $user->id);

            // OR: 参加しているDM/MPIM
            $q->orWhereHas('channel.users', function ($q2) use ($user) {
                $q2->where('user_id', $user->id)
                    ->whereNull('left_at');
            });
        });
    }

    /**
     * メッセージ一覧
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // per_page の安全処理
        $perPage = (int) $request->integer('per_page', 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        // 空文字は null 扱いに
        $workspaceId = $request->filled('workspace_id') ? $request->input('workspace_id') : null;
        $channelId   = $request->filled('channel_id')   ? $request->input('channel_id')   : null;
        $search      = $request->filled('search')       ? $request->input('search')       : null;
        $dateFrom    = $request->filled('date_from')    ? $request->input('date_from')    : null;
        $dateTo      = $request->filled('date_to')      ? $request->input('date_to')      : null;
        $messageType = $request->filled('message_type') ? $request->input('message_type') : 'all';

        $query = $this->buildAccessibleMessagesQuery($user);

        // 親メッセージ判定
        $query->where(function ($q) {
            $q->whereNull('thread_ts')                     // NULL
                ->orWhere('thread_ts', '=', '')              // 空文字
                ->orWhereColumn('thread_ts', 'slack_message_id'); // 親（自分自身と同じ）
        });

        if ($workspaceId !== null) {
            $query->where('messages.workspace_id', $workspaceId);
        }
        if ($channelId !== null) {
            $query->where('messages.channel_id', $channelId);
        }

        // 🔎 検索（ILIKE）
        if ($search) {
            $query->where('messages.text', 'ILIKE', "%{$search}%");
        }

        // 🔎 開始日・終了日（Slack の timestamp を使用）
        if ($dateFrom) {
            $fromTimestamp = Carbon::parse($dateFrom)->startOfDay()->valueOf() / 1000; // ← ms を秒に
            $query->where('messages.timestamp', '>=', $fromTimestamp);
        }

        if ($dateTo) {
            $toTimestamp = Carbon::parse($dateTo)->endOfDay()->valueOf() / 1000;
            $query->where('messages.timestamp', '<=', $toTimestamp);
        }

        if ($messageType !== 'all') {
            $query->where('messages.message_type', $messageType);
        }

        // ページング
        $paginator = $query
            ->orderBy('messages.timestamp', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        // グルーピング
        $grouped = collect($paginator->items())
            ->groupBy(function ($msg) {
                $ts = (int) floor($msg->timestamp);
                return \Carbon\Carbon::createFromTimestamp($ts)->format('Y-m-d');
            })
            ->map(fn($msgs, $date) => ['date' => $date, 'messages' => $msgs->values()])
            ->values();

        // デバッグログ出力
        Log::info('Paginator total: ' . $paginator->total());
        Log::info('Paginator items count: ' . count($paginator->items()));

        if ($paginator->firstItem()) {
            Log::info('First item ts: ' . $paginator->items()[0]->timestamp);
        }
        if ($paginator->lastItem()) {
            Log::info('Last item ts: ' . $paginator->items()[count($paginator->items()) - 1]->timestamp);
        }

        return Inertia::render('Messages/Index', [
            'groupedMessages' => $grouped,
            'filters' => [
                'workspace_id' => $workspaceId,
                'channel_id'   => $channelId,
                'search'       => $search,
                'date_from'    => $dateFrom,
                'date_to'      => $dateTo,
                'message_type' => $messageType,
                'per_page'     => $perPage,
            ],
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filterOptions' => [
                'workspaces'   => Workspace::all(['id', 'name']),
                'channels'     => Channel::all(['id', 'name', 'is_private', 'is_dm', 'workspace_id']),
                'messageTypes' => ['message', 'file', 'reaction'],
            ],
            'stats' => [
                'total_messages'      => Message::count(),
                'today_messages'      => Message::whereBetween('timestamp', [
                    now()->startOfDay()->timestamp,
                    now()->endOfDay()->timestamp
                ])->count(),
                'this_week_messages'  => Message::whereBetween('timestamp', [
                    now()->startOfWeek()->timestamp,
                    now()->endOfWeek()->timestamp
                ])->count(),
                'accessible_channels' => Channel::count(),
            ],
        ]);
    }

    /**
     * 検索API
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'workspace_id' => 'nullable|integer|exists:workspaces,id',
            'channel_id' => 'nullable|integer|exists:channels,id',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $user = Auth::user();
        $searchQuery = $request->input('query');
        $workspaceId = $request->input('workspace_id');
        $channelId = $request->input('channel_id');
        $limit = $request->input('limit', 20);

        $query = $this->buildAccessibleMessagesQuery($user);

        $query->where(function ($q) use ($searchQuery) {
            $q->where('messages.text', 'LIKE', "%{$searchQuery}%")
                ->orWhereHas('user', function ($uq) use ($searchQuery) {
                    $uq->where('name', 'LIKE', "%{$searchQuery}%");
                });
        });

        if ($workspaceId) {
            $query->where('messages.workspace_id', $workspaceId);
        }
        if ($channelId) {
            $query->where('messages.channel_id', $channelId);
        }

        $results = $query
            ->orderBy('messages.timestamp', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'results' => $results,
            'query' => $searchQuery,
            'count' => $results->count(),
        ]);
    }

    public function show(Message $message): \Inertia\Response
    {
        $message->load(['user', 'channel', 'workspace']);

        // スレッド返信を取得（もし存在するなら）
        $threadReplies = collect();
        if ($message->thread_ts) {
            $threadReplies = Message::where('thread_ts', $message->thread_ts)
                ->where('id', '!=', $message->id)
                ->with(['user'])
                ->orderBy('timestamp', 'asc')
                ->get();
        }

        return Inertia::render('Messages/Show', [
            'message' => $message,
            'threadReplies' => $threadReplies,
            'channelInfo' => [
                'id' => $message->channel->id,
                'name' => $message->channel->name,
                'is_private' => $message->channel->is_private,
                'is_dm' => $message->channel->is_dm,
            ]
        ]);
    }

    public function thread(Message $message): \Illuminate\Http\JsonResponse
    {
        $replies = Message::where('thread_ts', $message->thread_ts)
            ->where('id', '!=', $message->id)
            ->with(['user', 'files'])
            ->orderBy('timestamp', 'asc')
            ->get();

        return response()->json([
            'parent'  => $message->load(['user', 'files']),
            'replies' => $replies,
            'channel' => $message->channel,
            'workspace' => $message->workspace,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();

        // アクセス可能メッセージをベースに集計
        $query = $this->buildAccessibleMessagesQuery($user);

        $totalMessages = $query->count();
        $todayMessages = (clone $query)->whereDate('created_at', now())->count();
        $thisWeekMessages = (clone $query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $accessibleChannels = Channel::count();

        return response()->json([
            'total_messages'     => $totalMessages,
            'today_messages'     => $todayMessages,
            'this_week_messages' => $thisWeekMessages,
            'accessible_channels' => $accessibleChannels,
        ]);
    }

    // --- 省略（show / export / download はそのまま利用可） ---
}
