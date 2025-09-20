<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1時間毎の増分同期
Schedule::command('slack:sync')
    ->hourly()
    ->withoutOverlapping(60) // 60分のタイムアウト
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Hourly Slack sync completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Hourly Slack sync failed');
        // 管理者への通知
        Artisan::call('slack:monitor --notify-admins');
    });

// 日次フル同期（深夜2時）
Schedule::command('slack:sync --full')
    ->dailyAt('02:00')
    ->withoutOverlapping(180) // 3時間のタイムアウト
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Daily full Slack sync completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Daily full Slack sync failed');
        Artisan::call('slack:monitor --notify-admins');
    });

// 30分毎の同期監視とクリーンアップ
Schedule::command('slack:monitor --cleanup-old')
    ->everyThirtyMinutes()
    ->withoutOverlapping(15) // 15分のタイムアウト
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::debug('Slack sync monitoring completed');
    });

// 毎日4時にプログレス記録のクリーンアップ
Schedule::command('slack:monitor --cleanup-old --notify-admins')
    ->dailyAt('04:00')
    ->withoutOverlapping(30)
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Daily Slack sync cleanup and monitoring completed');
    });
