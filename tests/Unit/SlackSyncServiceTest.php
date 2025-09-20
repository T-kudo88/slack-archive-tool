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
use Illuminate\Support\Facades\Log;

class SlackSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;
    protected Workspace $workspace;
    protected Channel $publicChannel;
    protected Channel $dmChannel;
    protected SlackSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
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

        $this->workspace = Workspace::factory()->create([
            'slack_team_id' => 'T123456',
            'bot_token' => 'xoxb-bot-token'
        ]);

        $this->publicChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'C123456',
            'name' => 'general',
            'is_private' => false,
            'is_dm' => false
        ]);

        $this->dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'D123456',
            'name' => 'dm-user123456-user789012'
        ]);

        // Add users to DM channel
        $this->dmChannel->users()->attach([$this->user->id, $this->adminUser->id]);

        $this->service = new SlackSyncService($this->user, $this->workspace);
        
        // Mock users.info API call for all tests (to handle unknown users)
        Http::fake([
            'https://slack.com/api/users.info*' => Http::response([
                'ok' => true,
                'user' => [
                    'id' => 'U789012',
                    'name' => 'adminuser',
                    'real_name' => 'Admin User',
                    'profile' => [
                        'email' => 'admin@example.com',
                        'image_72' => 'https://example.com/admin.jpg'
                    ]
                ]
            ], 200)
        ]);
    }

    /** @test */
    public function it_can_sync_public_channel_messages()
    {
        // Mock Slack API response
        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456',
                        'text' => 'Hello world',
                        'ts' => '1609459200.000100'
                    ],
                    [
                        'type' => 'message',
                        'user' => 'U789012',
                        'text' => 'Hello back',
                        'ts' => '1609459260.000200'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $result = $this->service->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals('general', $result['channel']);
        $this->assertEquals(2, $result['messages_fetched']);
        $this->assertEquals(2, $result['messages_saved']);
        $this->assertEquals('incremental', $result['sync_type']);

        // Verify messages were saved
        $this->assertDatabaseHas('messages', [
            'channel_id' => $this->publicChannel->id,
            'slack_message_id' => '1609459200.000100',
            'text' => 'Hello world'
        ]);

        $this->assertDatabaseHas('messages', [
            'channel_id' => $this->publicChannel->id,
            'slack_message_id' => '1609459260.000200',
            'text' => 'Hello back'
        ]);
    }

    /** @test */
    public function it_performs_incremental_sync_correctly()
    {
        // Create existing message
        $existingMessage = Message::factory()->create([
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
        $this->assertEquals(1, $result['messages_fetched']);
        $this->assertEquals(1, $result['messages_saved']);

        // Verify API was called with correct oldest parameter
        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/conversations.history' &&
                   $request['oldest'] === '1609459100.000050';
        });
    }

    /** @test */
    public function it_applies_personal_data_restrictions_for_regular_users()
    {
        // Mock messages from different users
        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456', // Current user's message
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

        $result = $this->service->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['messages_fetched']);
        $this->assertEquals(2, $result['messages_saved']); // Public channel allows all messages

        // But for DM channel, only participant messages should be saved
        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456', // Participant
                        'text' => 'DM message from me',
                        'ts' => '1609459200.000100'
                    ],
                    [
                        'type' => 'message',
                        'user' => 'U789012', // Other participant
                        'text' => 'DM message from admin',
                        'ts' => '1609459260.000200'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $dmResult = $this->service->syncChannel($this->dmChannel);

        $this->assertTrue($dmResult['success']);
        $this->assertEquals(2, $dmResult['messages_saved']); // Both participants can access DM
    }

    /** @test */
    public function admin_can_sync_all_messages()
    {
        $adminService = new SlackSyncService($this->adminUser, $this->workspace);

        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
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
            ], 200)
        ]);

        $result = $adminService->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['messages_saved']);
    }

    /** @test */
    public function it_handles_paginated_responses()
    {
        // First page
        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::sequence()
                ->push([
                    'ok' => true,
                    'messages' => [
                        [
                            'type' => 'message',
                            'user' => 'U123456',
                            'text' => 'Message 1',
                            'ts' => '1609459200.000100'
                        ]
                    ],
                    'response_metadata' => ['next_cursor' => 'cursor123']
                ], 200)
                ->push([
                    'ok' => true,
                    'messages' => [
                        [
                            'type' => 'message',
                            'user' => 'U123456',
                            'text' => 'Message 2',
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

        // Verify second request used cursor
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'cursor=cursor123');
        });
    }

    /** @test */
    public function it_handles_slack_api_errors_gracefully()
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

        // Verify only one message with that timestamp exists
        $messageCount = Message::where('slack_message_id', '1609459200.000100')->count();
        $this->assertEquals(1, $messageCount);
    }

    /** @test */
    public function it_creates_users_from_slack_api()
    {
        Http::fake([
            'https://slack.com/api/conversations.history' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U999999', // Unknown user
                        'text' => 'Message from unknown user',
                        'ts' => '1609459200.000100'
                    ]
                ],
                'response_metadata' => []
            ], 200),
            'https://slack.com/api/users.info*' => Http::response([
                'ok' => true,
                'user' => [
                    'id' => 'U999999',
                    'name' => 'newuser',
                    'real_name' => 'New User',
                    'profile' => [
                        'email' => 'newuser@example.com',
                        'image_72' => 'https://example.com/avatar.jpg'
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->syncChannel($this->publicChannel);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['messages_saved']);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'slack_user_id' => 'U999999',
            'name' => 'New User',
            'email' => 'newuser@example.com'
        ]);
    }

    /** @test */
    public function it_can_sync_multiple_channels()
    {
        $channel2 = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'slack_channel_id' => 'C789012'
        ]);

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [
                    [
                        'type' => 'message',
                        'user' => 'U123456',
                        'text' => 'Test message',
                        'ts' => '1609459200.000100'
                    ]
                ],
                'response_metadata' => []
            ], 200)
        ]);

        $results = $this->service->syncMultipleChannels([
            $this->publicChannel->id,
            $channel2->id
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
    }

    /** @test */
    public function it_provides_sync_statistics()
    {
        // Create some test data
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
        $this->assertArrayHasKey('last_sync_times', $stats);

        $this->assertEquals(2, $stats['total_channels']); // public + dm
        $this->assertEquals(1, $stats['dm_channels']);
        $this->assertEquals(1, $stats['total_messages']);
        $this->assertEquals(1, $stats['user_messages']);
    }

    /** @test */
    public function it_uses_appropriate_token_for_channel_type()
    {
        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [],
                'response_metadata' => []
            ], 200)
        ]);

        // Sync DM channel
        $this->service->syncChannel($this->dmChannel);

        // Should use user token for DM
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer xoxp-user-token');
        });

        Http::fake([
            'https://slack.com/api/conversations.history*' => Http::response([
                'ok' => true,
                'messages' => [],
                'response_metadata' => []
            ], 200)
        ]);

        // Sync public channel
        $this->service->syncChannel($this->publicChannel);

        // Should use bot token for public channel
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer xoxb-bot-token');
        });
    }
}