<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class SlackSyncUsers extends Command
{
    protected $signature = 'slack:sync-users';
    protected $description = 'Slackから全ユーザーを同期してDBに保存';

    public function handle()
    {
        $token = env('SLACK_USER_TOKEN');
        $cursor = null;
        $count = 0;

        do {
            $params = ['limit' => 200];
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $response = Http::withToken($token)
                ->get('https://slack.com/api/users.list', $params)
                ->json();

            if (!isset($response['ok']) || !$response['ok']) {
                $this->error("Slack API error: " . ($response['error'] ?? 'unknown'));
                return 1;
            }

            foreach ($response['members'] as $member) {
                // Bot と削除済みユーザーはスキップ
                if ($member['is_bot'] || $member['id'] === 'USLACKBOT') {
                    continue;
                }

                User::updateOrCreate(
                    ['id' => $member['id']], // ← PKはSlackのuser.id
                    [
                        'name'       => $member['profile']['real_name'] ?? $member['name'],
                        'email'      => $member['profile']['email'] ?? null,
                        'avatar_url' => $member['profile']['image_192'] ?? null,
                        'is_active'  => !$member['deleted'],
                    ]
                );
                $count++;
            }

            $cursor = $response['response_metadata']['next_cursor'] ?? null;
        } while (!empty($cursor));

        $this->info("Slack users sync completed! Total synced: {$count}");
        return 0;
    }
}
