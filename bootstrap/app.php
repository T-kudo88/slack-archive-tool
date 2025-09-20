<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',   // ← これを追加！
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->append(\App\Http\Middleware\Cors::class);

        // Register custom middleware aliases
        $middleware->alias([
            'personal.data.restriction' => \App\Http\Middleware\PersonalDataRestriction::class,
            'flexible.auth' => \App\Http\Middleware\FlexibleAuth::class,
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // Temporarily disable CSRF for testing
        $middleware->validateCsrfTokens(except: [
            '*'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
