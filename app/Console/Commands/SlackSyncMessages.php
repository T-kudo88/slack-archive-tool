<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SlackApiService;
use App\Models\Workspace;

class SlackSyncMessages extends Command
{
    protected $signature = 'slack:sync-messages {channelId?}';
    protected $description = 'Slackからメッセージを同期する';

    public function handle()
    {
        $channelId = $this->argument('channelId');

        // 例1: .env に SLACK_BOT_TOKEN を設定している場合
        $token = config('services.slack.bot_token');

        // 例2: DB の workspaces テーブルから取得する場合（最初の1件）
        // $workspace = Workspace::first();
        // $token = decrypt($workspace->bot_token);

        if (!$token) {
            $this->error("Slack API Token が見つかりません。");
            return Command::FAILURE;
        }

        $slackApi = new SlackApiService($token);

        $messages = $slackApi->getChannelMessages($channelId);

        $this->info("取得メッセージ数: " . count($messages));
        return Command::SUCCESS;
    }
}
