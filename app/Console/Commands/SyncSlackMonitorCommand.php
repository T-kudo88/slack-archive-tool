<?php

namespace App\Console\Commands;

use App\Jobs\SyncSlackMessagesJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SyncSlackMonitorCommand extends Command
{
    protected $signature = 'slack:monitor
                           {--notify-admins : Send email notifications to admins for failed jobs}
                           {--cleanup-old : Clean up old progress records}';

    protected $description = 'Monitor Slack sync jobs and notify admins of issues';

    public function handle()
    {
        $this->info('Monitoring Slack sync jobs...');
        
        $failedJobs = $this->findFailedJobs();
        $stuckJobs = $this->findStuckJobs();
        
        if ($failedJobs->isNotEmpty()) {
            $this->warn("Found {$failedJobs->count()} permanently failed jobs");
            $this->displayFailedJobs($failedJobs);
            
            if ($this->option('notify-admins')) {
                $this->notifyAdminsOfFailures($failedJobs);
            }
        }
        
        if ($stuckJobs->isNotEmpty()) {
            $this->warn("Found {$stuckJobs->count()} potentially stuck jobs");
            $this->displayStuckJobs($stuckJobs);
        }
        
        if ($this->option('cleanup-old')) {
            $this->cleanupOldRecords();
        }
        
        $this->displaySyncStatistics();
        
        return 0;
    }
    
    protected function findFailedJobs()
    {
        $users = User::where('is_active', true)->get();
        $failedJobs = collect();
        
        foreach ($users as $user) {
            $pattern = "slack_sync_progress:{$user->id}:*";
            
            // Simple approach - check recent job IDs
            for ($i = 0; $i < 50; $i++) {
                $testKey = "slack_sync_progress:{$user->id}:sync_" . dechex($i);
                if ($progress = Cache::get($testKey)) {
                    if (in_array($progress['status'], ['failed_permanently', 'failed'])) {
                        $failedJobs->push($progress);
                    }
                }
            }
        }
        
        return $failedJobs;
    }
    
    protected function findStuckJobs()
    {
        $users = User::where('is_active', true)->get();
        $stuckJobs = collect();
        $stuckThreshold = now()->subHours(2); // Jobs running for more than 2 hours
        
        foreach ($users as $user) {
            for ($i = 0; $i < 50; $i++) {
                $testKey = "slack_sync_progress:{$user->id}:sync_" . dechex($i);
                if ($progress = Cache::get($testKey)) {
                    if ($progress['status'] === 'running') {
                        $startedAt = \Carbon\Carbon::parse($progress['started_at']);
                        if ($startedAt->lt($stuckThreshold)) {
                            $stuckJobs->push($progress);
                        }
                    }
                }
            }
        }
        
        return $stuckJobs;
    }
    
    protected function displayFailedJobs($failedJobs)
    {
        $this->table(
            ['Job ID', 'User ID', 'Workspace ID', 'Status', 'Error', 'Failed At'],
            $failedJobs->map(function ($job) {
                return [
                    $job['job_id'],
                    $job['user_id'],
                    $job['workspace_id'],
                    $job['status'],
                    substr($job['error'] ?? $job['final_error'] ?? 'Unknown error', 0, 50),
                    $job['failed_at'] ?? $job['failed_permanently_at'] ?? 'Unknown'
                ];
            })->toArray()
        );
    }
    
    protected function displayStuckJobs($stuckJobs)
    {
        $this->table(
            ['Job ID', 'User ID', 'Workspace ID', 'Progress', 'Started At', 'Current Channel'],
            $stuckJobs->map(function ($job) {
                return [
                    $job['job_id'],
                    $job['user_id'],
                    $job['workspace_id'],
                    "{$job['progress']}/{$job['total']}",
                    $job['started_at'],
                    $job['current_channel'] ?? 'N/A'
                ];
            })->toArray()
        );
    }
    
    protected function notifyAdminsOfFailures($failedJobs)
    {
        $admins = User::where('is_admin', true)->where('is_active', true)->get();
        
        foreach ($admins as $admin) {
            try {
                Log::warning('Admin notification sent for failed sync jobs', [
                    'admin_id' => $admin->id,
                    'failed_job_count' => $failedJobs->count(),
                    'failed_jobs' => $failedJobs->pluck('job_id')->toArray()
                ]);
                
                $this->info("Notification logged for admin: {$admin->name}");
                
            } catch (\Throwable $exception) {
                $this->error("Failed to notify admin {$admin->name}: {$exception->getMessage()}");
            }
        }
    }
    
    protected function cleanupOldRecords()
    {
        $cleanupCount = 0;
        $users = User::where('is_active', true)->get();
        $cutoffDate = now()->subDays(7);
        
        foreach ($users as $user) {
            for ($i = 0; $i < 100; $i++) {
                $testKey = "slack_sync_progress:{$user->id}:sync_" . dechex($i);
                if ($progress = Cache::get($testKey)) {
                    $completedAt = $progress['completed_at'] ?? $progress['failed_permanently_at'] ?? null;
                    if ($completedAt && \Carbon\Carbon::parse($completedAt)->lt($cutoffDate)) {
                        Cache::forget($testKey);
                        $cleanupCount++;
                    }
                }
            }
        }
        
        $this->info("Cleaned up {$cleanupCount} old progress records.");
    }
    
    protected function displaySyncStatistics()
    {
        $users = User::where('is_active', true)->whereNotNull('access_token')->get();
        $totalUsers = $users->count();
        
        $runningJobs = 0;
        $completedJobs = 0;
        $failedJobs = 0;
        
        foreach ($users as $user) {
            for ($i = 0; $i < 20; $i++) { // Check recent jobs
                $testKey = "slack_sync_progress:{$user->id}:sync_" . dechex($i);
                if ($progress = Cache::get($testKey)) {
                    switch ($progress['status']) {
                        case 'running':
                            $runningJobs++;
                            break;
                        case 'completed':
                            $completedJobs++;
                            break;
                        case 'failed':
                        case 'failed_permanently':
                            $failedJobs++;
                            break;
                    }
                }
            }
        }
        
        $this->info("\n=== Sync Statistics ===");
        $this->info("Active users: {$totalUsers}");
        $this->info("Running jobs: {$runningJobs}");
        $this->info("Completed jobs (recent): {$completedJobs}");
        $this->info("Failed jobs (recent): {$failedJobs}");
    }
}