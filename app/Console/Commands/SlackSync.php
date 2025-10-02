<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Message;
use App\Models\Channel;
use App\Models\User;
use App\Models\SlackFile;
use Illuminate\Support\Facades\Log;

class SlackSync extends Command
{
    protected $signature = 'slack:sync-all';
    protected $description = 'Slackの全チャンネル（DM含む）を同期して、全メッセージをDBに保存';

    public function handle()
    {
        $botToken  = config('services.slack.bot_token');
        $userToken = config('services.slack.user_token');

        // --- 全チャンネルを取得（bot_token 使用） ---
        $response = Http::withToken($botToken)->get('https://slack.com/api/conversations.list', [
            'types' => 'public_channel,private_channel,im,mpim',
            'limit' => 1000,
        ]);

        $channels = $response->json()['channels'] ?? [];
        $this->info("Channels count: " . count($channels));
        Log::info("[SlackSync] Channels count: " . count($channels));

        foreach ($channels as $channel) {
            $this->info("Fetching history for channel {$channel['id']}");

            // --- チャンネル保存 ---
            if ($channel['is_im'] ?? false) {
                // DM の場合
                $userId = $channel['user'] ?? null;
                $userName = null;

                if ($userId) {
                    $user = User::find($userId);
                    if ($user) {
                        $userName = $user->name;
                    } else {
                        // API からユーザー情報を取得
                        $userInfo = Http::withToken($userToken)->get('https://slack.com/api/users.info', [
                            'user' => $userId,
                        ])->json();

                        $userName = $userInfo['user']['real_name'] ?? $userInfo['user']['name'] ?? $userId;

                        User::updateOrCreate(
                            ['id' => $userId],
                            [
                                'name'      => $userName,
                                'email'     => $userId . '@slack.local',
                                'is_active' => true,
                                'is_admin'  => false,
                            ]
                        );
                    }
                }

                $channelName = "DM-" . ($userName ?? $userId ?? $channel['id']);
            } else {
                // 通常のチャンネル
                $channelName = $channel['name'] ?? "Channel-" . $channel['id'];
            }

            $channelModel = Channel::updateOrCreate(
                ['id' => $channel['id']],
                [
                    'workspace_id' => 1,
                    'name'         => $channelName,
                    'is_private'   => $channel['is_private'] ?? false,
                    'is_dm'        => $channel['is_im'] ?? false,
                    'is_mpim'      => $channel['is_mpim'] ?? false,
                    'is_archived'  => $channel['is_archived'] ?? false,
                    'member_count' => $channel['num_members'] ?? 0,
                ]
            );

            // --- 履歴を取得（user_token 使用） ---
            $cursor = null;
            do {
                $params = [
                    'channel' => $channel['id'],
                    'limit'   => 200,
                    'oldest'  => 0, // 全履歴を取得
                ];
                if ($cursor) {
                    $params['cursor'] = $cursor;
                }

                $history = Http::withToken($userToken)
                    ->get('https://slack.com/api/conversations.history', $params)
                    ->json();

                // --- エラーハンドリング ---
                if (($history['ok'] ?? false) === false) {
                    $error = $history['error'] ?? 'unknown_error';

                    if (in_array($error, ['channel_not_found', 'not_in_channel'])) {
                        $this->warn("Skip channel {$channel['id']} ({$error})");
                        continue 2; // このチャンネルはスキップ
                    }

                    $this->error("Failed to fetch messages for {$channel['id']}: {$error}");
                    continue 2;
                }

                foreach ($history['messages'] ?? [] as $msg) {
                    $this->storeMessage($channelModel, $msg);

                    // --- スレッド返信を取得 ---
                    if (isset($msg['thread_ts']) && !empty($msg['reply_count'])) {
                        $this->fetchReplies($channelModel, $msg['thread_ts'], $userToken);
                    }
                }

                $cursor = $history['response_metadata']['next_cursor'] ?? null;
            } while (!empty($cursor));
        }

        $this->info('Slack sync completed!');

        // --- 管理者権限を付与 ---
        $adminEmail = env('ADMIN_EMAIL'); // コメント: .env から管理者メールを取得
        if ($adminEmail) {
            \DB::table('users')
                ->where('email', $adminEmail)
                ->update(['is_admin' => true]);

            $this->info("Admin権限を {$adminEmail} に付与しました ✅");
        }
    }

    /**
     * メッセージを保存
     */
    private function storeMessage(Channel $channelModel, array $msg): void
    {
        $userId = $msg['user'] ?? null;
        $user = null;

        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $user = User::create([
                    'id'       => $userId,
                    'name'     => 'Unknown User',
                    'email'    => $userId . '@slack.local',
                    'is_active' => true,
                    'is_admin' => false,
                ]);
            }
        }

        // --- type 判定 ---
        $type = 'user';
        if (!empty($msg['subtype'])) {
            if ($msg['subtype'] === 'bot_message') {
                $type = 'bot';
            } else {
                $type = 'system';
            }
        } elseif (!empty($msg['bot_id'])) {
            $type = 'bot';
        } elseif (empty($msg['user'])) {
            $type = 'system';
        }

        // --- メッセージ保存 ---
        Message::updateOrCreate(
            ['slack_message_id' => (string) $msg['ts']],
            [
                'workspace_id' => 1,
                'channel_id'   => $channelModel->id,
                'user_id'      => $user?->id,
                'text'         => $this->replaceMentions($msg['text'] ?? ''),
                'timestamp'    => (string) $msg['ts'],
                'thread_ts'    => $msg['thread_ts'] ?? null,
                'reply_count'  => $msg['reply_count'] ?? 0,
                'type'         => $type,
                'metadata'     => [
                    'username' => $msg['username'] ?? null,
                    'subtype'  => $msg['subtype']  ?? null,
                    'bot_id'   => $msg['bot_id']   ?? null,
                    'app_id'   => $msg['app_id']   ?? null,
                ],
            ]
        );

        // --- 添付ファイル保存 ---
        if (!empty($msg['files'])) {
            foreach ($msg['files'] as $f) {
                SlackFile::updateOrCreate(
                    ['slack_file_id' => $f['id']],
                    [
                        'name'        => $f['name'] ?? null,
                        'title'       => $f['title'] ?? null,
                        'mimetype'    => $f['mimetype'] ?? null,
                        'url_private' => $f['url_private'] ?? null,
                        'channel_id'  => $channelModel->id,
                        'user_id'     => $user?->id,
                        'message_id'  => $msg['ts'],
                    ]
                );
            }
        }
    }

    /**
     * スレッド返信を取得
     */
    private function fetchReplies(Channel $channelModel, string $threadTs, string $userToken): void
    {
        $cursor = null;
        do {
            $params = [
                'channel' => $channelModel->id,
                'ts'      => $threadTs,
                'limit'   => 200,
            ];
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $replies = Http::withToken($userToken)
                ->get('https://slack.com/api/conversations.replies', $params)
                ->json();

            foreach ($replies['messages'] ?? [] as $reply) {
                $this->storeMessage($channelModel, $reply);
            }

            $cursor = $replies['response_metadata']['next_cursor'] ?? null;
        } while (!empty($cursor));
    }

    private function replaceMentions(string $text): string
    {
        return preg_replace_callback('/<@([A-Z0-9]+)>/', function ($matches) {
            $user = \App\Models\User::find($matches[1]);
            return $user ? '@' . $user->name : $matches[0];
        }, $text);
    }
}
