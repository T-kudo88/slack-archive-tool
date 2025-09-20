<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SlackSyncService
{
    private const SLACK_API_BASE = 'https://slack.com/api/';
    private const MESSAGES_PER_REQUEST = 200;
    private const REQUEST_DELAY_MS = 1000; // 1秒待機（レート制限対策）

    protected User $user;
    protected Workspace $workspace;
    protected ?string $botToken;
    protected ?string $userToken;

    public function __construct(User $user, Workspace $workspace)
    {
        $this->user = $user;
        $this->workspace = $workspace;
        $this->botToken = $workspace->bot_token;
        $this->userToken = $user->access_token;
    }

    /**
     * 指定チャンネルの増分同期を実行
     */
    public function syncChannel(Channel $channel, bool $fullSync = false): array
    {
        try {
            Log::info('Starting channel sync', [
                'channel_id' => $channel->id,
                'slack_channel_id' => $channel->slack_channel_id,
                'full_sync' => $fullSync,
                'user_id' => $this->user->id
            ]);

            // 最新同期日時を取得（増分同期用）
            $lastSyncTimestamp = $fullSync ? null : $this->getLastSyncTimestamp($channel);

            // メッセージ取得
            $messages = $this->fetchChannelMessages($channel, $lastSyncTimestamp);

            // メッセージ保存（個人制限適用）
            $savedCount = $this->saveMessages($messages, $channel);

            // 同期日時更新
            $this->updateChannelSyncTime($channel);

            $result = [
                'success' => true,
                'channel' => $channel->name,
                'messages_fetched' => count($messages),
                'messages_saved' => $savedCount,
                'sync_type' => $fullSync ? 'full' : 'incremental'
            ];

            Log::info('Channel sync completed', $result);
            return $result;
        } catch (Exception $e) {
            Log::error('Channel sync failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => $channel->name ?? 'unknown'
            ];
        }
    }

    /**
     * 複数チャンネルの一括同期
     */
    public function syncMultipleChannels(array $channelIds, bool $fullSync = false): array
    {
        $results = [];
        $channels = Channel::whereIn('id', $channelIds)
            ->where('workspace_id', $this->workspace->id)
            ->get();

        foreach ($channels as $channel) {
            $results[] = $this->syncChannel($channel, $fullSync);

            // レート制限対策
            if (count($results) < count($channels)) {
                usleep(self::REQUEST_DELAY_MS * 1000);
            }
        }

        return $results;
    }

    /**
     * ユーザーがアクセス可能な全チャンネルの同期
     */
    public function syncAccessibleChannels(bool $fullSync = false): array
    {
        $channels = $this->getAccessibleChannels();
        $channelIds = $channels->pluck('id')->toArray();

        return $this->syncMultipleChannels($channelIds, $fullSync);
    }

    /**
     * DMチャンネルの特別処理付き同期
     */
    public function syncDMChannels(bool $fullSync = false): array
    {
        $dmChannels = Channel::where('workspace_id', $this->workspace->id)
            ->where('is_dm', true)
            ->whereHas('users', function ($query) {
                $query->where('users.id', $this->user->id);
            })
            ->get();

        $results = [];
        foreach ($dmChannels as $channel) {
            // DMは個人制限が特に重要
            $result = $this->syncChannel($channel, $fullSync);
            $result['channel_type'] = 'dm';
            $results[] = $result;

            // レート制限対策
            usleep(self::REQUEST_DELAY_MS * 1000);
        }

        return $results;
    }

    /**
     * Slack Conversations APIからメッセージを取得
     */
    private function fetchChannelMessages(Channel $channel, ?string $oldest = null): array
    {
        $allMessages = [];
        $cursor = null;
        $token = $this->getAppropriateToken($channel);

        do {
            $params = [
                'channel' => $channel->slack_channel_id,
                'limit' => self::MESSAGES_PER_REQUEST,
                'include_all_metadata' => true,
                'inclusive' => true,
            ];

            if ($oldest) {
                $params['oldest'] = $oldest;
            }

            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            Log::debug('Fetching messages from Slack', [
                'channel' => $channel->slack_channel_id,
                'params' => $params
            ]);

            $response = Http::withToken($token)
                ->timeout(30)
                ->get(self::SLACK_API_BASE . 'conversations.history', $params);

            if (!$response->successful()) {
                throw new Exception("Slack API request failed: " . $response->status());
            }

            $data = $response->json();

            if (!$data['ok']) {
                throw new Exception("Slack API error: " . ($data['error'] ?? 'unknown'));
            }

            $messages = $data['messages'] ?? [];
            $allMessages = array_merge($allMessages, $messages);

            // ページネーション処理
            $cursor = $data['response_metadata']['next_cursor'] ?? null;

            Log::debug('Fetched message batch', [
                'count' => count($messages),
                'has_more' => !empty($cursor)
            ]);

            // レート制限対策
            if ($cursor) {
                usleep(self::REQUEST_DELAY_MS * 1000);
            }
        } while ($cursor);

        Log::info('Total messages fetched', [
            'channel' => $channel->name,
            'total_count' => count($allMessages)
        ]);

        return $allMessages;
    }

    /**
     * メッセージをデータベースに保存（個人制限適用）
     */
    private function saveMessages(array $messages, Channel $channel): int
    {
        $savedCount = 0;

        foreach ($messages as $messageData) {
            try {
                if (!$this->canUserAccessMessage($messageData, $channel)) {
                    continue;
                }

                // 既存メッセージを探す
                $existingMessage = Message::where('slack_message_id', $messageData['ts'])
                    ->where('channel_id', $channel->id)
                    ->first();

                if ($existingMessage) {
                    $message = $existingMessage;
                } else {
                    // 新規メッセージ作成
                    $user = $this->getOrCreateUser($messageData);
                    if (!$user) {
                        continue;
                    }

                    $message = Message::create([
                        'workspace_id' => $this->workspace->id,
                        'channel_id' => $channel->id,
                        'user_id' => $user->id,
                        'slack_message_id' => $messageData['ts'],
                        'text' => $messageData['text'] ?? '',
                        'thread_ts' => $messageData['thread_ts'] ?? null,
                        'timestamp' => $messageData['ts'],
                        'reply_count' => $messageData['reply_count'] ?? 0,
                        'message_type' => $messageData['type'] ?? 'message',
                        'has_files' => !empty($messageData['files']),
                        'reactions' => $messageData['reactions'] ?? null,
                        'metadata' => $this->extractMetadata($messageData),
                    ]);

                    $savedCount++;
                }

                // ファイルは常に処理する（新規でも既存でも）
                if (!empty($messageData['files'])) {
                    foreach ($messageData['files'] as $fileData) {
                        Log::info('Saving Slack file', [
                            'file_id' => $fileData['id'] ?? null,
                            'message_id' => $message->id,
                            'name' => $fileData['name'] ?? null,
                            'mimetype' => $fileData['mimetype'] ?? null,
                        ]);

                        \App\Models\SlackFile::updateOrCreate(
                            ['slack_file_id' => $fileData['id']],
                            [
                                'message_id' => $message->id,
                                'name' => $fileData['name'] ?? null,
                                'title' => $fileData['title'] ?? null,
                                'mimetype' => $fileData['mimetype'] ?? null,
                                'file_type' => explode('/', $fileData['mimetype'])[0] ?? 'other',
                                'size' => $fileData['size'] ?? 0,
                                'url_private' => $fileData['url_private'] ?? null,
                                'url_private_download' => $fileData['url_private_download'] ?? null,
                                'user_id' => $message->user_id,
                                'channel_id' => $message->channel_id,
                                'workspace_id' => $message->workspace_id,
                                'is_public' => false,
                                'metadata' => $fileData,
                            ]
                        );
                    }
                }
            } catch (Exception $e) {
                Log::warning('Failed to save message', [
                    'message_ts' => $messageData['ts'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $savedCount;
    }

    /**
     * 個人制限チェック：ユーザーがこのメッセージにアクセスできるか
     */
    private function canUserAccessMessage(array $messageData, Channel $channel): bool
    {
        // 管理者は全メッセージアクセス可能
        if ($this->user->is_admin) {
            return true;
        }

        // 自分のメッセージは常にアクセス可能
        if (isset($messageData['user']) && $messageData['user'] === $this->user->slack_user_id) {
            return true;
        }

        // DMチャンネルの場合、参加者のみアクセス可能
        if ($channel->is_dm) {
            return $channel->users()->where('users.id', $this->user->id)->exists();
        }

        // プライベートチャンネルの場合、メンバーのみアクセス可能
        if ($channel->is_private) {
            return $channel->users()->where('users.id', $this->user->id)->exists();
        }

        // パブリックチャンネルは基本的にアクセス可能
        return true;
    }

    /**
     * ユーザーがアクセス可能なチャンネルを取得
     */
    private function getAccessibleChannels()
    {
        $query = Channel::where('workspace_id', $this->workspace->id);

        if (!$this->user->is_admin) {
            // 一般ユーザーの場合
            $query->where(function ($q) {
                // パブリックチャンネル
                $q->where('is_private', false)
                    // または参加しているプライベートチャンネル・DM
                    ->orWhereHas('users', function ($userQuery) {
                        $userQuery->where('users.id', $this->user->id);
                    });
            });
        }

        return $query->get();
    }

    /**
     * 適切なトークンを取得（DMかどうかで判断）
     */
    private function getAppropriateToken(Channel $channel): string
    {
        // DMの場合はUser Token、そうでなければBot Token
        if ($channel->is_dm && $this->userToken) {
            return $this->userToken;
        }

        if ($this->botToken) {
            return $this->botToken;
        }

        throw new Exception('No appropriate token available for channel access');
    }

    /**
     * 最終同期タイムスタンプを取得
     */
    private function getLastSyncTimestamp(Channel $channel): ?string
    {
        $lastMessage = Message::where('channel_id', $channel->id)
            ->orderBy('timestamp', 'desc')
            ->first();

        return $lastMessage ? $lastMessage->timestamp : null;
    }

    /**
     * チャンネルの同期時刻を更新
     */
    private function updateChannelSyncTime(Channel $channel): void
    {
        $channel->update(['last_synced_at' => now()]);
    }

    /**
     * メッセージが既に存在するかチェック
     */
    private function messageExists(string $timestamp, Channel $channel): bool
    {
        return Message::where('slack_message_id', $timestamp)
            ->where('channel_id', $channel->id)
            ->exists();
    }

    /**
     * ユーザーを取得または作成
     */
    private function getOrCreateUser(array $messageData): ?User
    {
        if (!isset($messageData['user'])) {
            return null;
        }

        $slackUserId = $messageData['user'];

        // 既存ユーザーを検索
        $user = User::where('slack_user_id', $slackUserId)->first();

        if ($user) {
            return $user;
        }

        // 新規ユーザーの場合、Slack APIから詳細情報を取得
        try {
            $userInfo = $this->fetchUserInfo($slackUserId);
            if (!$userInfo) {
                return null;
            }

            return User::create([
                'slack_user_id' => $slackUserId,
                'name' => $userInfo['real_name'] ?? $userInfo['name'] ?? 'Unknown User',
                'email' => $userInfo['profile']['email'] ?? $slackUserId . '@slack.local',
                'avatar_url' => $userInfo['profile']['image_72'] ?? null,
                'is_admin' => false,
                'is_active' => true,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to fetch user info from Slack', [
                'slack_user_id' => $slackUserId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Slack APIからユーザー情報を取得
     */
    private function fetchUserInfo(string $slackUserId): ?array
    {
        $response = Http::withToken($this->botToken)
            ->get(self::SLACK_API_BASE . 'users.info', [
                'user' => $slackUserId
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        return $data['ok'] ? $data['user'] : null;
    }

    /**
     * メッセージからメタデータを抽出
     */
    private function extractMetadata(array $messageData): array
    {
        $metadata = [];

        // ファイル情報
        if (!empty($messageData['files'])) {
            $metadata['files'] = array_map(function ($file) {
                return [
                    'id' => $file['id'] ?? null,
                    'name' => $file['name'] ?? null,
                    'mimetype' => $file['mimetype'] ?? null,
                    'size' => $file['size'] ?? null,
                    'url_private' => $file['url_private'] ?? null,
                ];
            }, $messageData['files']);
        }

        // Bot情報
        if (!empty($messageData['bot_id'])) {
            $metadata['bot_id'] = $messageData['bot_id'];
        }

        // アプリ情報
        if (!empty($messageData['app_id'])) {
            $metadata['app_id'] = $messageData['app_id'];
        }

        // 編集情報
        if (!empty($messageData['edited'])) {
            $metadata['edited'] = $messageData['edited'];
        }

        return $metadata;
    }

    /**
     * 同期統計情報を取得
     */
    public function getSyncStats(): array
    {
        $stats = [
            'total_channels' => Channel::where('workspace_id', $this->workspace->id)->count(),
            'accessible_channels' => $this->getAccessibleChannels()->count(),
            'dm_channels' => Channel::where('workspace_id', $this->workspace->id)
                ->where('is_dm', true)
                ->whereHas('users', function ($query) {
                    $query->where('users.id', $this->user->id);
                })
                ->count(),
            'total_messages' => Message::where('workspace_id', $this->workspace->id)->count(),
            'user_messages' => Message::where('workspace_id', $this->workspace->id)
                ->where('user_id', $this->user->id)
                ->count(),
            'last_sync_times' => Channel::where('workspace_id', $this->workspace->id)
                ->whereNotNull('last_synced_at')
                ->orderBy('last_synced_at', 'desc')
                ->limit(5)
                ->pluck('last_synced_at', 'name')
                ->toArray()
        ];

        return $stats;
    }
}
