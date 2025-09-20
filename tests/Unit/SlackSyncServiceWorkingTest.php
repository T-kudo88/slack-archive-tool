<?php

namespace Tests\Unit;

use App\Services\SlackSyncService;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SlackSyncServiceWorkingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function service_can_be_instantiated()
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        $service = new SlackSyncService($user, $workspace);
        
        $this->assertInstanceOf(SlackSyncService::class, $service);
    }

    /** @test */
    public function service_provides_sync_statistics()
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        $channel = Channel::factory()->create([
            'workspace_id' => $workspace->id
        ]);
        
        Message::factory()->create([
            'workspace_id' => $workspace->id,
            'channel_id' => $channel->id,
            'user_id' => $user->id
        ]);

        $service = new SlackSyncService($user, $workspace);
        $stats = $service->getSyncStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_channels', $stats);
        $this->assertArrayHasKey('accessible_channels', $stats);
        $this->assertArrayHasKey('dm_channels', $stats);
        $this->assertArrayHasKey('total_messages', $stats);
        $this->assertArrayHasKey('user_messages', $stats);
        $this->assertArrayHasKey('last_sync_times', $stats);

        $this->assertEquals(1, $stats['total_channels']);
        $this->assertEquals(1, $stats['total_messages']);
        $this->assertEquals(1, $stats['user_messages']);
    }

    /** @test */
    public function admin_user_can_access_all_channels()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $workspace = Workspace::factory()->create();

        $publicChannel = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'is_private' => false,
            'is_dm' => false
        ]);

        $privateChannel = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'is_private' => true,
            'is_dm' => false
        ]);

        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $workspace->id
        ]);

        // Admin service
        $adminService = new SlackSyncService($adminUser, $workspace);
        $adminStats = $adminService->getSyncStats();

        // Regular user service
        $regularService = new SlackSyncService($regularUser, $workspace);
        $regularStats = $regularService->getSyncStats();

        // Admin should see all channels
        $this->assertEquals(3, $adminStats['accessible_channels']);

        // Regular user should see only public channel
        $this->assertEquals(1, $regularStats['accessible_channels']);
    }

    /** @test */
    public function dm_participant_logic_works()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $workspace->id
        ]);

        // Add participants
        $dmChannel->users()->attach([$user1->id, $user2->id]);

        $service = new SlackSyncService($user1, $workspace);
        $stats = $service->getSyncStats();

        // User should have access to 1 DM channel
        $this->assertEquals(1, $stats['dm_channels']);
    }

    /** @test */
    public function personal_data_restriction_logic()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $adminUser = User::factory()->create(['is_admin' => true]);
        $workspace = Workspace::factory()->create();

        $publicChannel = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'is_private' => false,
            'is_dm' => false
        ]);

        $privateDmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $workspace->id
        ]);

        // Don't add regular user to private DM
        $privateDmChannel->users()->attach([$adminUser->id]);

        $service = new SlackSyncService($user, $workspace);
        $stats = $service->getSyncStats();

        // Regular user should only see public channel
        $this->assertEquals(1, $stats['accessible_channels']);
        $this->assertEquals(0, $stats['dm_channels']);

        // Admin service
        $adminService = new SlackSyncService($adminUser, $workspace);
        $adminStats = $adminService->getSyncStats();

        // Admin should see both channels
        $this->assertEquals(2, $adminStats['accessible_channels']);
        $this->assertEquals(1, $adminStats['dm_channels']);
    }

    /** @test */
    public function service_handles_workspace_with_no_channels()
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $service = new SlackSyncService($user, $workspace);
        $stats = $service->getSyncStats();

        $this->assertEquals(0, $stats['total_channels']);
        $this->assertEquals(0, $stats['accessible_channels']);
        $this->assertEquals(0, $stats['dm_channels']);
        $this->assertEquals(0, $stats['total_messages']);
        $this->assertEquals(0, $stats['user_messages']);
    }

    /** @test */
    public function service_tracks_last_sync_times()
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $channel1 = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'channel1',
            'last_synced_at' => now()->subHour()
        ]);

        $channel2 = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'channel2',
            'last_synced_at' => now()->subMinutes(30)
        ]);

        $service = new SlackSyncService($user, $workspace);
        $stats = $service->getSyncStats();

        $this->assertIsArray($stats['last_sync_times']);
        $this->assertArrayHasKey('channel1', $stats['last_sync_times']);
        $this->assertArrayHasKey('channel2', $stats['last_sync_times']);
    }
}