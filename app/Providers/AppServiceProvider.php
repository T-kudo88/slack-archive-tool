<?php

namespace App\Providers;

use App\Models\SlackFile;
use App\Policies\FilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Vite::prefetch(concurrency: 3);
        \URL::forceScheme('https');

        // Register file policies
        Gate::policy(SlackFile::class, FilePolicy::class);

        // Define additional gates for file operations
        Gate::define('view-file', [FilePolicy::class, 'view']);
        Gate::define('delete-file', [FilePolicy::class, 'delete']);
        Gate::define('download-file', [FilePolicy::class, 'download']);
        Gate::define('make-file-public', [FilePolicy::class, 'makePublic']);
        Gate::define('make-file-private', [FilePolicy::class, 'makePrivate']);

        // Admin access gate
        Gate::define('admin-access', function ($user) {
            return $user && $user->role === 'admin';
        });
    }
}
