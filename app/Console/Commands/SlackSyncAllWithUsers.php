<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SlackSyncAllWithUsers extends Command
{
    protected $signature = 'slack:sync-all-with-users';
    protected $description = 'Slackユーザーを同期してから全メッセージを同期する複合処理';

    public function handle()
    {
        $this->info("Step 1: ユーザー同期を開始します...");
        $exitCodeUsers = Artisan::call('slack:sync-users');
        if ($exitCodeUsers !== 0) {
            $this->error("ユーザー同期に失敗しました。処理を中断します。");
            return Command::FAILURE;
        }
        $this->info("✅ ユーザー同期完了");

        $this->info("Step 2: 全メッセージ同期を開始します...");
        $exitCodeMessages = Artisan::call('slack:sync-all');
        if ($exitCodeMessages !== 0) {
            $this->error("メッセージ同期に失敗しました。");
            return Command::FAILURE;
        }
        $this->info("✅ 全メッセージ同期完了");

        return Command::SUCCESS;
    }
}
