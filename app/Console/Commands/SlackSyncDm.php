<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Message;
use App\Models\Channel;
use App\Models\User;
use App\Models\SlackFile;

class SlackSyncDm extends Command
{
    protected $signature = 'slack:sync-dm {channel_id}';
    protected $description = '指定したSlackのDMチャンネルを同期してDBに保存';

    public function handle()
    {
        $token = config('services.slack.user_token');
        $channelId = $this->argument('channel_id');

        $this->info("Fetching DM history for channel: {$channelId}");

        // --- チャンネルをDBに保存（なければ作成） ---
        $channelModel = Channel::updateOrCreate(
            ['id' => $channelId],
            [
                'workspace_id' => 1,
                'name'         => "DM-{$channelId}",
                'is_private'   => true,
                'is_dm'        => true,
                'is_mpim'      => false,
                'is_archived'  => false,
                'member_count' => 2,
            ]
        );

        // --- 履歴を取得（ページネーション対応） ---
        $cursor = null;
        do {
            $params = ['channel' => $channelId, 'limit' => 200];
            if ($cursor) $params['cursor'] = $cursor;

            $history = Http::withToken($token)
                ->get('https://slack.com/api/conversations.history', $params)
                ->json();

            dump($history); // 👈 ここに一時追加

            if (!isset($history['messages'])) {
                $this->warn("No messages found for {$channelId}");
                break;
            }

            foreach ($history['messages'] as $msg) {
                $this->storeMessage($channelModel, $msg);

                // --- スレッド返信も取得 ---
                $replyCount = $msg['reply_count'] ?? 0;
                if (isset($msg['thread_ts']) && $replyCount > 0) {
                    $this->fetchReplies($channelModel, $msg['thread_ts']);
                }
            }

            $cursor = $history['response_metadata']['next_cursor'] ?? null;
        } while (!empty($cursor));

        $this->info("DM sync completed for {$channelId}!");
    }

    /**
     * メッセージ保存
     */
    private function storeMessage(Channel $channelModel, array $msg): void
    {
        $userId = $msg['user'] ?? null;
        $user = $userId ? User::where('slack_user_id', $userId)->first() : null;


        Message::updateOrCreate(
            ['slack_message_id' => (string) $msg['ts']],  // ← string
            [
                'workspace_id' => 1,
                'channel_id'   => $channelModel->id,
                'user_id'      => $user?->id,
                'text'         => $msg['text'] ?? '',
                'timestamp'    => (string) $msg['ts'],    // ← string
                'thread_ts'    => isset($msg['thread_ts']) ? (string) $msg['thread_ts'] : null,
                'reply_count'  => $msg['reply_count'] ?? 0,
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
                        'user_id'     => $msg['user'] ?? null,
                        'message_id'  => (string) $msg['ts'],   // ← 文字列キャスト必須！
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
        $token = config('services.slack.user_token');
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
