<?php

namespace Tests\Unit;

use App\Services\SlackSyncService;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class SlackSyncServiceSimpleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;
    protected Workspace $workspace;
    protected Channel $publicChannel;
    protected SlackSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'slack_user_id' => 'U123456',
            'is_admin' => false,
            'access_token' => 'xoxp-user-token'
        ]);

        $this->adminUser = User::factory()->create([
            'slack_user_id' => 'U789012',
            'is_admin' => true,
            'access_token' => 'xoxp-admin-token'
        ]);

        $this->workspace = Workspace::factory()->create([
            'bot_token' => 'xoxb-bot-token'
        ]);

        $this->publicChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'C123456',
            'is_private' => false,
            'is_dm' => false
        ]);

        $this->service = new SlackSyncService($this->user, $this->workspace);
    }

    /** @test */
    public function it_can_sync_messages_from_existing_users()
    {
        // Mock Slack API response with messages from existing users only
        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456', // Existing user
                        'text' => 'Hello world',
                        'ts' => '1609459200.000100'
                    ],
                    [
                        'type' => 'message',
                        'user' => 'U789012', // Existing admin user
                        'text' => 'Hello back',
                        'ts' => '1609459260.000200'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $result = $this->service->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['messages_fetched']);
        $this->assertEquals(2, $result['messages_saved']);
        $this->assertEquals('incremental', $result['sync_type']);

        // Verify messages were saved
        $this->assertDatabaseHas('messages', [
            'channel_id' => $this->publicChannel->id,
            'slack_message_id' => '1609459200.000100',
            'text' => 'Hello world',
            'user_id' => $this->user->id
        ]);

        $this->assertDatabaseHas('messages', [
            'channel_id' => $this->publicChannel->id,
            'slack_message_id' => '1609459260.000200',
            'text' => 'Hello back',
            'user_id' => $this->adminUser->id
        ]);
    }

    /** @test */
    public function it_performs_incremental_sync_with_timestamp()
    {
        // Create existing message
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->user->id,
            'slack_message_id' => '1609459100.000050',
            'timestamp' => '1609459100.000050'
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456',
                        'text' => 'New message after sync',
                        'ts' => '1609459200.000100'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $result = $this->service->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['messages_saved']);

        // Verify API was called with correct oldest parameter
        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/conversations.history' &&
                   $request['oldest'] === '1609459100.000050';
        });
    }

    /** @test */
    public function it_handles_slack_api_errors()
    {
        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => false,
                'error' => 'channel_not_found'
            ], 200)
        ]);

        $result = $this->service->syncChannel($this->publicChannel);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('channel_not_found', $result['error']);
        $this->assertEquals(0, Message::count());
    }

    /** @test */
    public function it_skips_duplicate_messages()
    {
        // Create existing message
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->user->id,
            'slack_message_id' => '1609459200.000100'
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456',
                        'text' => 'Duplicate message',
                        'ts' => '1609459200.000100' // Same timestamp
                    ],
                    [
                        'type' => 'message',
                        'user' => 'U123456',
                        'text' => 'New message',
                        'ts' => '1609459260.000200'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $result = $this->service->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['messages_fetched']);
        $this->assertEquals(1, $result['messages_saved']); // Only new message saved

        // Verify only one message with the duplicate timestamp exists
        $messageCount = Message::where('slack_message_id', '1609459200.000100')->count();
        $this->assertEquals(1, $messageCount);
    }

    /** @test */
    public function it_applies_personal_data_restrictions()
    {
        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'D123456'
        ]);

        // Add user as DM participant
        $dmChannel->users()->attach($this->user->id);

        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456', // User's own message
                        'text' => 'My DM message',
                        'ts' => '1609459200.000100'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $result = $this->service->syncChannel($dmChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['messages_saved']);

        // Verify message was saved
        $this->assertDatabaseHas('messages', [
            'channel_id' => $dmChannel->id,
            'user_id' => $this->user->id,
            'text' => 'My DM message'
        ]);
    }

    /** @test */
    public function admin_can_access_all_messages()
    {
        $adminService = new SlackSyncService($this->adminUser, $this->workspace);

        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456', // Regular user's message
                        'text' => 'Regular user message',
                        'ts' => '1609459200.000100'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $result = $adminService->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['messages_saved']);
    }

    /** @test */
    public function it_provides_sync_statistics()
    {
        // Create test data
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id
        ]);

        $stats = $this->service->getSyncStats();

        $this->assertArrayHasKey('total_channels', $stats);
        $this->assertArrayHasKey('accessible_channels', $stats);
        $this->assertArrayHasKey('dm_channels', $stats);
        $this->assertArrayHasKey('total_messages', $stats);
        $this->assertArrayHasKey('user_messages', $stats);

        $this->assertEquals(1, $stats['total_channels']);
        $this->assertEquals(1, $stats['total_messages']);
        $this->assertEquals(1, $stats['user_messages']);
    }

    /** @test */
    public function it_uses_bot_token_for_public_channels()
    {
        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [],
                'response_metadata' => []
            ], 200)
        ]);

        $this->service->syncChannel($this->publicChannel);

        // Should use bot token for public channel
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer xoxb-bot-token');
        });
    }

    /** @test */
    public function it_uses_user_token_for_dm_channels()
    {
        $dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [],
                'response_metadata' => []
            ], 200)
        ]);

        $this->service->syncChannel($dmChannel);

        // Should use user token for DM channel
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer xoxp-user-token');
        });
    }

    /** @test */
    public function full_sync_ignores_existing_messages()
    {
        // Create existing message
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->user->id,
            'timestamp' => '1609459100.000050'
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [],
                'response_metadata' => []
            ], 200)
        ]);

        $result = $this->service->syncChannel($this->publicChannel, true); // Full sync

        $this->assertTrue($result['success']);
        $this->assertEquals('full', $result['sync_type']);

        // Verify API was called without oldest parameter for full sync
        Http::assertSent(function ($request) {
            return !array_key_exists('oldest', $request->data());
        });
    }
}