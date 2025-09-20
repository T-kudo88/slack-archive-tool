<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PersonalDataRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected User $regularUser;
    protected User $adminUser;
    protected User $otherUser;
    protected Workspace $workspace;
    protected Channel $publicChannel;
    protected Channel $dmChannel;
    protected Channel $privateDmChannel;
    protected Message $ownMessage;
    protected Message $otherUserMessage;
    protected Message $dmMessage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->regularUser = User::factory()->create([
            'slack_user_id' => 'U123456',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'slack_user_id' => 'U789012',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'slack_user_id' => 'U345678',
            'is_admin' => false,
            'is_active' => true,
        ]);

        // Create test workspace and channels
        $this->workspace = Workspace::factory()->create();
        
        $this->publicChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_private' => false,
            'is_dm' => false,
            'name' => 'general',
        ]);

        $this->dmChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_private' => true,
            'is_dm' => true,
            'name' => 'dm-user123456-user345678',
        ]);

        $this->privateDmChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_private' => true,
            'is_dm' => true,
            'name' => 'dm-user789012-user999999',
        ]);

        // Create test messages
        $this->ownMessage = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'text' => 'My own message',
        ]);

        $this->otherUserMessage = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->otherUser->id,
            'text' => 'Other user message',
        ]);

        $this->dmMessage = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->dmChannel->id,
            'user_id' => $this->otherUser->id,
            'text' => 'DM message',
        ]);

        // Test routes are defined in the actual test methods
    }

    private function setupTestRoutes()
    {
        Route::middleware(['auth', 'personal.data.restriction'])->group(function () {
            Route::get('/test/messages/{message}', function (Message $message) {
                return response()->json(['message' => $message]);
            });
            Route::get('/test/channels/{channel}/messages', function (Channel $channel) {
                return response()->json(['messages' => $channel->messages]);
            });
            Route::get('/test/users/{user}/messages', function (User $user) {
                return response()->json(['messages' => $user->messages]);
            });
        });
    }

    /** @test */
    public function regular_user_can_access_their_own_messages()
    {
        $this->setupTestRoutes();
        $this->actingAs($this->regularUser);

        $response = $this->get("/test/messages/{$this->ownMessage->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => [
                'id' => $this->ownMessage->id,
                'text' => 'My own message',
            ]
        ]);
    }

    /** @test */
    public function regular_user_cannot_access_other_users_messages()
    {
        $this->setupTestRoutes();
        $this->actingAs($this->regularUser);

        $response = $this->get("/test/messages/{$this->otherUserMessage->id}");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Access denied: You can only access your own messages']);
    }

    /** @test */
    public function regular_user_cannot_access_dm_they_are_not_participant_of()
    {
        $this->actingAs($this->regularUser);

        $response = $this->get("/test/messages/{$this->dmMessage->id}");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Access denied: You are not a participant in this DM']);
    }

    /** @test */
    public function admin_can_access_any_message_with_audit_log()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get("/test/messages/{$this->otherUserMessage->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => [
                'id' => $this->otherUserMessage->id,
                'text' => 'Other user message',
            ]
        ]);

        // Check audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action' => 'access_user_message',
            'resource_type' => 'message',
            'resource_id' => $this->otherUserMessage->id,
            'accessed_user_id' => $this->otherUser->id,
        ]);
    }

    /** @test */
    public function admin_can_access_any_dm_with_audit_log()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get("/test/channels/{$this->dmChannel->id}/messages");

        $response->assertStatus(200);

        // Check audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action' => 'access_dm_channel',
            'resource_type' => 'channel',
            'resource_id' => $this->dmChannel->id,
        ]);
    }

    /** @test */
    public function regular_user_can_access_public_channel_messages()
    {
        $this->actingAs($this->regularUser);

        $response = $this->get("/test/channels/{$this->publicChannel->id}/messages");

        $response->assertStatus(200);
        $response->assertJsonStructure(['messages']);
    }

    /** @test */
    public function privilege_escalation_attack_is_prevented()
    {
        // Regular user tries to access admin functionality by manipulating request
        $this->actingAs($this->regularUser);

        // Try to access other user's messages by modifying user_id in header/parameter
        $response = $this->withHeaders([
            'X-User-Override' => $this->adminUser->id,
            'X-Admin-Access' => 'true'
        ])->get("/test/messages/{$this->otherUserMessage->id}");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Access denied: You can only access your own messages']);
    }

    /** @test */
    public function session_hijacking_protection_works()
    {
        $this->actingAs($this->regularUser);

        // First request establishes baseline
        $response = $this->get("/test/messages/{$this->ownMessage->id}");
        $response->assertStatus(200);

        // Simulate session hijacking attempt with different IP
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100']);
        
        $response = $this->get("/test/messages/{$this->otherUserMessage->id}");

        // Should still be denied based on ownership, not IP change
        $response->assertStatus(403);
    }

    /** @test */
    public function inactive_user_cannot_access_any_data()
    {
        $inactiveUser = User::factory()->create([
            'is_active' => false,
            'is_admin' => false,
        ]);

        $this->actingAs($inactiveUser);

        $response = $this->get("/test/messages/{$this->ownMessage->id}");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Access denied: Account is inactive']);
    }

    /** @test */
    public function dm_participant_verification_works_correctly()
    {
        // Create channel_users relationship for DM
        $this->regularUser->channels()->attach($this->dmChannel->id);
        $this->otherUser->channels()->attach($this->dmChannel->id);

        $this->actingAs($this->regularUser);

        $response = $this->get("/test/messages/{$this->dmMessage->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => [
                'id' => $this->dmMessage->id,
                'text' => 'DM message',
            ]
        ]);
    }

    /** @test */
    public function bulk_access_request_is_rate_limited()
    {
        $this->actingAs($this->regularUser);

        // Make multiple rapid requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->get("/test/messages/{$this->ownMessage->id}");
            
            if ($i < 5) {
                $response->assertStatus(200);
            } else {
                // Should be rate limited after 5 requests
                $response->assertStatus(429);
                break;
            }
        }
    }

    /** @test */
    public function admin_access_to_user_data_requires_justification()
    {
        $this->actingAs($this->adminUser);

        // Admin access without justification should be logged with warning
        $response = $this->get("/test/users/{$this->regularUser->id}/messages");

        $response->assertStatus(200);

        // Check audit log includes warning for unjustified access
        $this->assertDatabaseHas('audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action' => 'access_user_data',
            'resource_type' => 'user',
            'resource_id' => $this->regularUser->id,
            'notes' => 'Admin accessed user data without explicit justification',
        ]);
    }

    /** @test */
    public function admin_access_with_justification_is_properly_logged()
    {
        $this->actingAs($this->adminUser);

        $response = $this->withHeaders([
            'X-Access-Justification' => 'User support ticket #12345'
        ])->get("/test/users/{$this->regularUser->id}/messages");

        $response->assertStatus(200);

        // Check audit log includes justification
        $this->assertDatabaseHas('audit_logs', [
            'admin_user_id' => $this->adminUser->id,
            'action' => 'access_user_data',
            'resource_type' => 'user',
            'resource_id' => $this->regularUser->id,
            'notes' => 'User support ticket #12345',
        ]);
    }

    /** @test */
    public function middleware_handles_non_existent_resources_gracefully()
    {
        $this->actingAs($this->regularUser);

        $response = $this->get("/test/messages/99999");

        $response->assertStatus(404);
    }

    /** @test */
    public function middleware_prevents_sql_injection_in_parameters()
    {
        $this->actingAs($this->regularUser);

        // Try SQL injection in parameter
        $maliciousId = "1; DROP TABLE messages; --";
        
        $response = $this->get("/test/messages/" . urlencode($maliciousId));

        $response->assertStatus(404); // Should treat as invalid ID, not execute SQL
        
        // Verify messages table still exists
        $this->assertDatabaseHas('messages', [
            'id' => $this->ownMessage->id
        ]);
    }
}