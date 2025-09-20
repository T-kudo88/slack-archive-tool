<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Message;
use App\Models\Channel;
use App\Models\User;
use App\Models\SlackFile;

class SlackSync extends Command
{
    protected $signature = 'slack:sync-all';
    protected $description = 'Slackの全チャンネル（DM含む）を同期して、全メッセージをDBに保存';

    public function handle()
    {
        $token = env('SLACK_USER_TOKEN');

        // --- 全チャンネルを取得 ---
        $response = Http::withToken($token)->get('https://slack.com/api/conversations.list', [
            'types' => 'public_channel,private_channel,im,mpim',
            'limit' => 1000,
        ]);

        $channels = $response->json()['channels'] ?? [];
        $this->info("Channels count: " . count($channels));

        foreach ($channels as $channel) {
            $this->info("Fetching history for channel {$channel['id']}");

            // --- チャンネル保存 ---
            $channelModel = Channel::updateOrCreate(
                ['id' => $channel['id']],
                [
                    'workspace_id' => 1, // TODO: 複数ワークスペース対応は将来追加
                    'name'         => $channel['name']
                        ?? ($channel['is_im'] ? 'DM-' . ($channel['user'] ?? $channel['id']) : 'Channel-' . $channel['id']),
                    'is_private'   => $channel['is_private'] ?? false,
                    'is_dm'        => $channel['is_im'] ?? false,
                    'is_mpim'      => $channel['is_mpim'] ?? false,
                    'is_archived'  => $channel['is_archived'] ?? false,
                    'member_count' => $channel['num_members'] ?? 0,
                ]
            );

            // --- 全履歴を取得（ページネーション対応） ---
            $cursor = null;
            do {
                $params = ['channel' => $channel['id'], 'limit' => 200];
                if ($cursor) $params['cursor'] = $cursor;

                $history = Http::withToken($token)
                    ->get('https://slack.com/api/conversations.history', $params)
                    ->json();

                if (!isset($history['messages'])) {
                    $this->warn("No messages for {$channel['id']}");
                    break;
                }

                foreach ($history['messages'] as $msg) {
                    $this->storeMessage($channelModel, $msg);

                    // --- スレッド返信を取得 ---
                    if (isset($msg['thread_ts']) && isset($msg['reply_count']) && $msg['reply_count'] > 0) {
                        $this->fetchReplies($channelModel, $msg['thread_ts']);
                    }
                }

                $cursor = $history['response_metadata']['next_cursor'] ?? null;
            } while (!empty($cursor));
        }

        $this->info('Slack sync completed!');
    }

    /**
     * メッセージを保存
     */
    private function storeMessage(Channel $channelModel, array $msg): void
    {
        $user = isset($msg['user']) ? User::find($msg['user']) : null;

        Message::updateOrCreate(
            ['slack_message_id' => $msg['ts']],
            [
                'workspace_id' => 1,
                'channel_id'   => $channelModel->id,
                'user_id'      => $user?->id,
                'text'         => $msg['text'] ?? '',
                'timestamp'    => $msg['ts'],
                'thread_ts'    => $msg['thread_ts'] ?? null,
                'reply_count'  => $msg['reply_count'] ?? 0,
            ]
        );

        // --- 添付ファイル保存 ---
        if (!empty($msg['files'])) {
            foreach ($msg['files'] as $f) {
                SlackFile::updateOrCreate(
                    ['slack_file_id' => $f['id']],
                    [
                        'name'       => $f['name'] ?? null,
                        'title'      => $f['title'] ?? null,
                        'mimetype'   => $f['mimetype'] ?? null,
                        'url_private' => $f['url_private'] ?? null,
                        'channel_id' => $channelModel->id,
                        'user_id'    => $msg['user'] ?? null,
                        'message_id' => $msg['ts'],
                    ]
                );
            }
        }
    }

    /**
     * スレッド返信を取得
     */
    private function fetchReplies(Channel $channelModel, string $threadTs): void
    {
        $token = env('SLACK_USER_TOKEN');

        $cursor = null;
        do {
            $params = ['channel' => $channelModel->id, 'ts' => $threadTs, 'limit' => 200];
            if ($cursor) $params['cursor'] = $cursor;

            $replies = Http::withToken($token)
                ->get('https://slack.com/api/conversations.replies', $params)
                ->json();

            foreach ($replies['messages'] ?? [] as $reply) {
                $this->storeMessage($channelModel, $reply);
            }

            $cursor = $replies['response_metadata']['next_cursor'] ?? null;
        } while (!empty($cursor));
    }
}
