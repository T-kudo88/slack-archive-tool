<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SlackService; // Slack API 用サービスを作る想定

class FetchSlackMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:fetch-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch messages from Slack API and store them into DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching messages from Slack...');

        // サービス呼び出し（あとで作る）
        app(SlackService::class)->fetchMessages();

        $this->info('Messages fetched successfully.');
        return 0;
    }
}
