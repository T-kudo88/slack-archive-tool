<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\DmController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Auth\SlackController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\SyncController as AdminSyncController;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

// ==========================================
// ブラウザセッション専用ルート
// ==========================================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // APIトークン管理
    Route::get('/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::get('/api-tokens/info', [App\Http\Controllers\ApiTokenController::class, 'show'])->name('api-tokens.show');
    Route::post('/api-tokens/generate', [App\Http\Controllers\ApiTokenController::class, 'generate'])->name('api-tokens.generate');
    Route::post('/api-tokens/regenerate', [App\Http\Controllers\ApiTokenController::class, 'regenerate'])->name('api-tokens.regenerate');
    Route::delete('/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'revoke'])->name('api-tokens.revoke');
});

// ==========================================
// ブラウザ + API両対応ルート
// ==========================================
Route::middleware('flexible.auth')->group(function () {
    // Messages
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/search', [MessageController::class, 'search'])->name('messages.search');
    Route::get('/messages/stats', [MessageController::class, 'stats'])->name('messages.stats');

    Route::middleware('personal.data.restriction')->group(function () {
        Route::post('/messages/export', [MessageController::class, 'export'])->name('messages.export');
        Route::get('/messages/download/{filename}', [MessageController::class, 'download'])->name('messages.download');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
    });

    // Channels
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');

    // DMs
    Route::get('/dms', [DmController::class, 'index'])->name('dms.index');

    // Files
    // Files
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::post('/files', [FileController::class, 'store'])->name('files.store');

    // 固定ルートはここに置く！
    Route::get('/files/statistics', [FileController::class, 'statistics'])->name('files.statistics');
    Route::post('/files/bulk-delete', [FileController::class, 'bulkDelete'])->name('files.bulk-delete');

    // 動的ルートは最後に置く
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}/thumbnail/{size?}', [FileController::class, 'thumbnail'])->name('files.thumbnail');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
});

// ==========================================
// 管理者ルート (admin/*)
// ==========================================
Route::prefix('admin')
    ->middleware(['auth', 'admin'])
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('admin.audit');
        Route::get('/sync-status', [AdminSyncController::class, 'index'])->name('admin.sync');
        Route::get('/security', function () {
            return Inertia::render('Admin/Security/Index');
        })->name('admin.security');

        // もし health-check が必要ならここに追加
        Route::get('/health-check', function () {
            return response()->json(['status' => 'ok']);
        })->name('admin.health');
    });

Route::get('/debug-auth', function () {
    return [
        'check' => Auth::check(),
        'user' => Auth::user(),
        'session' => session()->all(),
    ];
});

// ==========================================
// Slack OAuth
// ==========================================
Route::get('/auth/slack/redirect', [SlackController::class, 'redirect'])->name('slack.redirect');
Route::get('/auth/slack/callback', [SlackController::class, 'callback'])->name('slack.callback');

require __DIR__ . '/auth.php';
