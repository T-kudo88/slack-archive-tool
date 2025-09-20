<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SlackController;
use Illuminate\Support\Facades\Route;

// Slack OAuth Authentication Routes (Primary Authentication Method)
Route::middleware('guest')->group(function () {
    // Custom login page that only shows Slack OAuth
    Route::get('login', function () {
        return inertia('Auth/SlackLogin');
    })->name('login');
    
    // Disable default registration - users must use Slack OAuth
    Route::get('register', function () {
        return redirect()->route('login')->with('info', 'Slackアカウントでログインしてください。');
    })->name('register');
});

// Keep only logout functionality from standard auth
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// Disable all other standard Laravel auth routes (password reset, email verification, etc.)
// Users must authenticate via Slack OAuth only
