<?php

namespace Tests\Feature;

use App\Services\SlackSyncService;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class SlackSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $regularUser;
    protected User $adminUser;
    protected User $otherUser;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regularUser = User::factory()->create([
            'slack_user_id' => 'U123456',
            'is_admin' => false,
            'is_active' => true,
            'access_token' => 'xoxp-user-token'
        ]);

        $this->adminUser = User::factory()->create([
            'slack_user_id' => 'U789012',
            'is_admin' => true,
            'is_active' => true,
            'access_token' => 'xoxp-admin-token'
        ]);

        $this->otherUser = User::factory()->create([
            'slack_user_id' => 'U999999',
            'is_admin' => false,
            'is_active' => true
        ]);

        $this->workspace = Workspace::factory()->create([
            'slack_team_id' => 'T123456',
            'bot_token' => 'xoxb-bot-token'
        ]);
    }

    /** @test */
    public function regular_user_sync_respects_personal_data_restrictions()
    {
        // Create channels
        $publicChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'C123456',
            'is_private' => false,
            'is_dm' => false
        ]);

        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'D123456'
        ]);

        $privateDmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'D789012'
        ]);

        // Add regular user to one DM but not the other
        $dmChannel->users()->attach($this->regularUser->id);
        $privateDmChannel->users()->attach([$this->adminUser->id, $this->otherUser->id]);

        // Mock Slack API responses
        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456', // Regular user's message
                        'text' => 'My message',
                        'ts' => '1609459200.000100'
                    ],
                    [
                        'type' => 'message',
                        'user' => 'U999999', // Other user's message
                        'text' => 'Other user message',
                        'ts' => '1609459260.000200'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $service = new SlackSyncService($this->regularUser, $this->workspace);

        // Sync accessible channels
        $results = $service->syncAccessibleChannels();

        // Should sync public channel and accessible DM channel
        $this->assertCount(2, $results); // Only accessible channels
        
        // Check that only appropriate channels were synced
        $syncedChannelIds = collect($results)->filter(function ($result) {
            return $result['success'];
        })->count();

        $this->assertEquals(2, $syncedChannelIds);

        // Verify messages were saved according to personal data restrictions
        $savedMessages = Message::where('workspace_id', $this->workspace->id)->get();
        $this->assertGreaterThan(0, $savedMessages->count());

        // All saved messages should be accessible to regular user
        foreach ($savedMessages as $message) {
            $this->assertTrue(
                $message->user_id === $this->regularUser->id || // Own message
                (!$message->channel->is_private && !$message->channel->is_dm) || // Public channel
                $message->channel->users()->where('users.id', $this->regularUser->id)->exists() // DM participant
            );
        }
    }

    /** @test */
    public function admin_user_can_sync_all_channels_and_messages()
    {
        $publicChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_private' => false,
            'is_dm' => false
        ]);

        $privateChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_private' => true,
            'is_dm' => false
        ]);

        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id
        ]);

        // Admin is not explicitly added to private channel or DM
        // but should still be able to sync them

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U999999', // Any user
                        'text' => 'Any message',
                        'ts' => '1609459200.000100'
                    ]
                ],
                'response_metadata' => []
            ], 200),
            'https://slack.com/api/users.info*' => Http::response([
                'ok' => true,
                'user' => [
                    'id' => 'U999999',
                    'name' => 'testuser',
                    'real_name' => 'Test User',
                    'profile' => [
                        'email' => 'test@example.com'
                    ]
                ]
            ], 200)
        ]);

        $adminService = new SlackSyncService($this->adminUser, $this->workspace);
        $results = $adminService->syncAccessibleChannels();

        // Admin should be able to sync all channels
        $this->assertCount(3, $results);

        $successfulSyncs = collect($results)->where('success', true)->count();
        $this->assertEquals(3, $successfulSyncs);
    }

    /** @test */
    public function dm_sync_maintains_participant_relationships()
    {
        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'D123456'
        ]);

        // Add participants
        $dmChannel->users()->attach([$this->regularUser->id, $this->otherUser->id]);

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456', // Regular user
                        'text' => 'Hello from me',
                        'ts' => '1609459200.000100'
                    ],
                    [
                        'type' => 'message',
                        'user' => 'U999999', // Other participant
                        'text' => 'Hello back',
                        'ts' => '1609459260.000200'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $service = new SlackSyncService($this->regularUser, $this->workspace);
        $results = $service->syncDMChannels();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('dm', $results[0]['channel_type']);

        // Both messages should be saved since both users are DM participants
        $messages = Message::where('channel_id', $dmChannel->id)->get();
        $this->assertCount(2, $messages);

        // Verify relationship is maintained
        $this->assertTrue($dmChannel->users()->where('users.id', $this->regularUser->id)->exists());
        $this->assertTrue($dmChannel->users()->where('users.id', $this->otherUser->id)->exists());
    }

    /** @test */
    public function sync_service_provides_accurate_statistics()
    {
        // Create test data
        $publicChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_private' => false,
            'is_dm' => false
        ]);

        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id
        ]);

        $privateDmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id
        ]);

        // Add user to one DM
        $dmChannel->users()->attach($this->regularUser->id);

        // Create some messages
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $publicChannel->id,
            'user_id' => $this->regularUser->id
        ]);

        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $dmChannel->id,
            'user_id' => $this->otherUser->id
        ]);

        $service = new SlackSyncService($this->regularUser, $this->workspace);
        $stats = $service->getSyncStats();

        $this->assertEquals(3, $stats['total_channels']); // All channels in workspace
        $this->assertEquals(2, $stats['accessible_channels']); // Public + accessible DM
        $this->assertEquals(1, $stats['dm_channels']); // Only accessible DM
        $this->assertEquals(2, $stats['total_messages']); // All messages in workspace
        $this->assertEquals(1, $stats['user_messages']); // Only regular user's messages

        $this->assertIsArray($stats['last_sync_times']);
    }

    /** @test */
    public function sync_handles_network_errors_gracefully()
    {
        $channel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response(null, 500)
        ]);

        $service = new SlackSyncService($this->regularUser, $this->workspace);
        $result = $service->syncChannel($channel);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function full_sync_ignores_existing_timestamps()
    {
        $channel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'C123456'
        ]);

        // Create existing message
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $channel->id,
            'user_id' => $this->regularUser->id,
            'timestamp' => '1609459100.000050'
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456',
                        'text' => 'Full sync message',
                        'ts' => '1609459200.000100'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $service = new SlackSyncService($this->regularUser, $this->workspace);
        $result = $service->syncChannel($channel, true); // Full sync

        $this->assertTrue($result['success']);
        $this->assertEquals('full', $result['sync_type']);

        // Verify API was called without oldest parameter
        Http::assertSent(function ($request) {
            return !array_key_exists('oldest', $request->data());
        });
    }

    /** @test */
    public function sync_updates_channel_last_synced_timestamp()
    {
        $channel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'last_synced_at' => null
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [],
                'response_metadata' => []
            ], 200)
        ]);

        $service = new SlackSyncService($this->regularUser, $this->workspace);
        $service->syncChannel($channel);

        $channel->refresh();
        $this->assertNotNull($channel->last_synced_at);
        $this->assertTrue($channel->last_synced_at->isToday());
    }
}