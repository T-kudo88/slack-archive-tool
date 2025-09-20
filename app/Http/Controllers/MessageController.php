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

        $workspaceId = $request->input('workspace_id');
        $channelId = $request->input('channel_id');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $messageType = $request->input('message_type', 'all');
        $limit = min((int)$request->input('limit', 500), 1000); // 最大1000件

        $query = $this->buildAccessibleMessagesQuery($user);

        if ($workspaceId) {
            $query->where('messages.workspace_id', $workspaceId);
        }
        if ($channelId) {
            $query->where('messages.channel_id', $channelId);
        }
        if ($search) {
            $query->where('messages.text', 'LIKE', "%{$search}%");
        }
        if ($dateFrom) {
            $query->whereDate('messages.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('messages.created_at', '<=', $dateTo);
        }
        if ($messageType !== 'all') {
            $query->where('messages.message_type', $messageType);
        }

        $messages = $query
            ->orderBy('messages.timestamp', 'asc')
            ->limit($limit)
            ->get();

        \Log::info('Fetched messages', [
            'count' => $messages->count(),
            'sample' => $messages->take(5)->toArray(),
        ]);

        // 日付ごとにグルーピング
        $grouped = $messages->groupBy(function ($msg) {
            // Slackのtimestampは "1754804994.729649" 形式なので整数部だけ取る
            $ts = (int) floor($msg->timestamp);
            return \Carbon\Carbon::createFromTimestamp($ts)->format('Y-m-d');
        })->map(function ($msgs, $date) {
            return [
                'date' => $date,
                'messages' => $msgs,
            ];
        })->values();

        \Log::info('Inertia render data', [
            'groupedMessages' => $grouped->toArray(),
        ]);

        return Inertia::render('Messages/Index', [
            'groupedMessages' => $grouped->toArray(),
            'filters' => [
                'workspace_id' => $workspaceId,
                'channel_id' => $channelId,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'message_type' => $messageType,
                'limit' => $limit,
            ],
            'filterOptions' => [
                'workspaces' => Workspace::all(['id', 'name']),
                'channels'   => Channel::all(['id', 'name', 'is_private', 'is_dm']),
                'messageTypes' => ['message', 'file', 'reaction'],
            ],
            'stats' => [
                'total_messages'     => Message::count(),
                'today_messages'     => Message::whereDate('created_at', now())->count(),
                'this_week_messages' => Message::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
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
