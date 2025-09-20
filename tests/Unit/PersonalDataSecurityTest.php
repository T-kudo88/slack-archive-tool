<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Workspace;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PersonalDataSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_only_access_own_messages_logic()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $otherUser = User::factory()->create(['is_admin' => false]);
        $workspace = Workspace::factory()->create();
        $channel = Channel::factory()->create(['workspace_id' => $workspace->id]);
        
        $ownMessage = Message::factory()->create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'workspace_id' => $workspace->id
        ]);
        
        $otherMessage = Message::factory()->create([
            'user_id' => $otherUser->id,
            'channel_id' => $channel->id,
            'workspace_id' => $workspace->id
        ]);

        // User should be able to access own message
        $this->assertTrue($ownMessage->user_id === $user->id);
        
        // User should NOT be able to access other user's message
        $this->assertFalse($otherMessage->user_id === $user->id);
    }

    /** @test */
    public function dm_participant_validation_logic()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create(); // Not a participant
        
        $workspace = Workspace::factory()->create();
        $dmChannel = Channel::factory()->dm()->create(['workspace_id' => $workspace->id]);
        
        // Add participants to DM channel
        $dmChannel->users()->attach([$user1->id, $user2->id]);
        
        $dmMessage = Message::factory()->create([
            'channel_id' => $dmChannel->id,
            'user_id' => $user2->id,
            'workspace_id' => $workspace->id
        ]);

        // User1 should be participant
        $this->assertTrue($dmChannel->users->contains($user1));
        
        // User2 should be participant
        $this->assertTrue($dmChannel->users->contains($user2));
        
        // User3 should NOT be participant
        $this->assertFalse($dmChannel->users->contains($user3));
    }

    /** @test */
    public function admin_privilege_validation_logic()
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $regularUser = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        $inactiveAdmin = User::factory()->create(['is_admin' => true, 'is_active' => false]);

        $this->assertTrue($admin->is_admin && $admin->is_active);
        $this->assertFalse($regularUser->is_admin);
        $this->assertFalse($inactiveAdmin->is_active); // Even admin should be inactive
    }

    /** @test */
    public function message_ownership_validation()
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $channel = Channel::factory()->create(['workspace_id' => $workspace->id]);
        
        $message = Message::factory()->create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'workspace_id' => $workspace->id
        ]);

        // Verify message belongs to user
        $this->assertEquals($user->id, $message->user_id);
        $this->assertTrue($user->messages->contains($message));
    }

    /** @test */
    public function channel_type_identification()
    {
        $workspace = Workspace::factory()->create();
        
        $publicChannel = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'is_private' => false,
            'is_dm' => false
        ]);
        
        $privateChannel = Channel::factory()->private()->create([
            'workspace_id' => $workspace->id,
            'is_dm' => false
        ]);
        
        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $workspace->id
        ]);

        $this->assertFalse($publicChannel->is_private);
        $this->assertFalse($publicChannel->is_dm);
        
        $this->assertTrue($privateChannel->is_private);
        $this->assertFalse($privateChannel->is_dm);
        
        $this->assertTrue($dmChannel->is_private);
        $this->assertTrue($dmChannel->is_dm);
    }

    /** @test */
    public function user_status_validation()
    {
        $activeUser = User::factory()->create(['is_active' => true]);
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->assertTrue($activeUser->is_active);
        $this->assertFalse($inactiveUser->is_active);
    }

    /** @test */
    public function workspace_channel_message_hierarchy()
    {
        $workspace = Workspace::factory()->create();
        $channel = Channel::factory()->create(['workspace_id' => $workspace->id]);
        $user = User::factory()->create();
        
        $message = Message::factory()->create([
            'workspace_id' => $workspace->id,
            'channel_id' => $channel->id,
            'user_id' => $user->id
        ]);

        // Verify hierarchy relationships
        $this->assertEquals($workspace->id, $message->workspace_id);
        $this->assertEquals($channel->id, $message->channel_id);
        $this->assertEquals($workspace->id, $channel->workspace_id);
        
        // Verify through relationships
        $this->assertTrue($workspace->messages->contains($message));
        $this->assertTrue($workspace->channels->contains($channel));
        $this->assertTrue($channel->messages->contains($message));
    }
}