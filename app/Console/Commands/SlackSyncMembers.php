<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Channel;
use App\Models\User;

class SlackSyncMembers extends Command
{
    protected $signature = 'slack:sync-members {--types=im,mpim}';
    protected $description = 'Sync members for IM/MPIM channels into channel_users pivot';

    public function handle()
    {
        $token = env('SLACK_USER_TOKEN');
        if (!$token) {
            $this->error('SLACK_USER_TOKEN not set.');
            return Command::FAILURE;
        }

        // 自分の Slack User ID を取得（DMの相手特定の保険）
        $auth = Http::withToken($token)->get('https://slack.com/api/auth.test')->json();
        $selfSlackUid = $auth['user_id'] ?? null;

        // IM/MPIM の一覧
        $types = $this->option('types'); // 既定: im,mpim
        $resp = Http::withToken($token)->get('https://slack.com/api/conversations.list', [
            'types' => $types,
            'limit' => 1000,
        ])->json();

        $channels = $resp['channels'] ?? [];
        $this->info("Target channels: " . count($channels));

        foreach ($channels as $ch) {
            $cid = $ch['id'];
            $isDm = (bool)($ch['is_im'] ?? false);
            $isMpim = (bool)($ch['is_mpim'] ?? false);

            // members API（ページング対応）
            $members = [];
            $cursor = null;
            do {
                $params = ['channel' => $cid, 'limit' => 1000];
                if ($cursor) $params['cursor'] = $cursor;

                $mres = Http::withToken($token)->get('https://slack.com/api/conversations.members', $params)->json();

                if (!($mres['ok'] ?? false)) {
                    // IMでmembers取れないケースのフォールバック
                    if ($isDm && !empty($ch['user']) && $selfSlackUid) {
                        $members = [$selfSlackUid, $ch['user']];
                        break;
                    }
                    $this->warn("members fetch failed for {$cid}: " . ($mres['error'] ?? 'unknown'));
                    break;
                }

                $members = array_merge($members, $mres['members'] ?? []);
                $cursor = $mres['response_metadata']['next_cursor'] ?? null;
            } while (!empty($cursor));

            $members = array_values(array_unique($members));
            $this->info("{$cid}: " . count($members) . " members");

            // DBへ反映（channel_users ピボット）
            DB::transaction(function () use ($cid, $members) {
                foreach ($members as $slackUid) {
                    $user = User::where('slack_user_id', $slackUid)->first();
                    if (!$user) {
                        // ユーザー未同期ならスキップ（先に slack:sync-users を実行しておく前提）
                        continue;
                    }
                    DB::table('channel_users')->updateOrInsert(
                        ['channel_id' => $cid, 'user_id' => $user->id],
                        ['joined_at' => now(), 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            });
        }

        $this->info('DM/MPIM members sync completed!');
        return Command::SUCCESS;
    }
}
