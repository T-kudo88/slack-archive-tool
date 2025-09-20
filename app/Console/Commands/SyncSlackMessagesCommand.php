<?php

namespace App\Console\Commands;

use App\Jobs\SyncSlackMessagesJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSlackMessagesCommand extends Command
{
    protected $signature = 'slack:sync 
                           {--user-id= : Specific user ID to sync}
                           {--workspace-id= : Specific workspace ID to sync}
                           {--full : Perform full sync instead of incremental}
                           {--channels= : Comma-separated channel IDs to sync}';

    protected $description = 'Sync Slack messages for all users or specific user/workspace';

    public function handle()
    {
        $this->info('Starting Slack message synchronization...');
        
        $userId = $this->option('user-id');
        $workspaceId = $this->option('workspace-id');
        $fullSync = $this->option('full');
        $channelIds = $this->option('channels') ? explode(',', $this->option('channels')) : [];
        
        $query = User::where('is_active', true)->whereNotNull('access_token');
        
        if ($userId) {
            $query->where('id', $userId);
        }
        
        $users = $query->get();
        
        if ($users->isEmpty()) {
            $this->error('No active users with access tokens found.');
            return 1;
        }
        
        $jobCount = 0;
        
        foreach ($users as $user) {
            $workspaceQuery = Workspace::query();
            
            if ($workspaceId) {
                $workspaceQuery->where('id', $workspaceId);
            }
            
            $workspaces = $workspaceQuery->get();
            
            foreach ($workspaces as $workspace) {
                try {
                    SyncSlackMessagesJob::dispatch(
                        $user,
                        $workspace,
                        $channelIds,
                        $fullSync,
                        'scheduled'
                    );
                    
                    $jobCount++;
                    
                    $this->info("Queued sync job for user {$user->name} in workspace {$workspace->name}");
                    
                    Log::info('Scheduled sync job queued', [
                        'user_id' => $user->id,
                        'workspace_id' => $workspace->id,
                        'full_sync' => $fullSync,
                        'channel_count' => count($channelIds),
                        'sync_type' => 'scheduled'
                    ]);
                    
                } catch (\Throwable $exception) {
                    $this->error("Failed to queue sync job for user {$user->name}: {$exception->getMessage()}");
                    
                    Log::error('Failed to queue scheduled sync job', [
                        'user_id' => $user->id,
                        'workspace_id' => $workspace->id,
                        'error' => $exception->getMessage()
                    ]);
                }
            }
        }
        
        $this->info("Successfully queued {$jobCount} sync jobs.");
        
        return 0;
    }
}