<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Services\SlackUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SlackUserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SlackUserService $slackUserService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slackUserService = new SlackUserService();
    }

    /** @test */
    public function it_creates_new_user_from_slack_data()
    {
        $slackUserData = [
            'id' => 'U12345',
            'name' => 'Test User',
            'real_name' => 'Test User Real',
            'profile' => [
                'email' => 'test@example.com',
                'image_72' => 'https://example.com/avatar.jpg',
            ],
        ];

        $user = $this->slackUserService->createOrUpdateUser($slackUserData, 'xoxp-token');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('U12345', $user->slack_user_id);
        $this->assertEquals('Test User Real', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar_url);
        $this->assertEquals('xoxp-token', $user->access_token);
        $this->assertTrue($user->is_active);
    }

    /** @test */
    public function it_updates_existing_user_by_slack_id()
    {
        $existingUser = User::factory()->create([
            'slack_user_id' => 'U12345',
            'name' => 'Old Name',
            'avatar_url' => 'old-avatar.jpg',
        ]);

        $slackUserData = [
            'id' => 'U12345',
            'name' => 'Updated Name',
            'real_name' => 'Updated Real Name',
            'profile' => [
                'email' => $existingUser->email,
                'image_72' => 'https://example.com/new-avatar.jpg',
            ],
        ];

        $user = $this->slackUserService->createOrUpdateUser($slackUserData, 'new-token');

        $this->assertEquals($existingUser->id, $user->id);
        $this->assertEquals('Updated Real Name', $user->name);
        $this->assertEquals('https://example.com/new-avatar.jpg', $user->avatar_url);
        $this->assertEquals('new-token', $user->access_token);
    }

    /** @test */
    public function it_updates_existing_user_by_email()
    {
        $existingUser = User::factory()->create([
            'email' => 'test@example.com',
            'slack_user_id' => null,
        ]);

        $slackUserData = [
            'id' => 'U12345',
            'name' => 'Slack Name',
            'real_name' => 'Slack Real Name',
            'profile' => [
                'email' => 'test@example.com',
                'image_72' => 'https://example.com/avatar.jpg',
            ],
        ];

        $user = $this->slackUserService->createOrUpdateUser($slackUserData, 'xoxp-token');

        $this->assertEquals($existingUser->id, $user->id);
        $this->assertEquals('U12345', $user->slack_user_id);
        $this->assertEquals('Slack Real Name', $user->name);
        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar_url);
    }

    /** @test */
    public function it_handles_missing_email_in_slack_data()
    {
        $slackUserData = [
            'id' => 'U12345',
            'name' => 'Test User',
            'real_name' => 'Test User Real',
            'profile' => [
                'image_72' => 'https://example.com/avatar.jpg',
                // email is missing
            ],
        ];

        $user = $this->slackUserService->createOrUpdateUser($slackUserData, 'xoxp-token');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('U12345', $user->slack_user_id);
        $this->assertEquals('Test User Real', $user->name);
        $this->assertEquals('U12345@slack.local', $user->email); // Generated email
    }

    /** @test */
    public function it_prefers_real_name_over_display_name()
    {
        $slackUserData = [
            'id' => 'U12345',
            'name' => 'display_name',
            'real_name' => 'Real Name',
            'profile' => [
                'email' => 'test@example.com',
                'image_72' => 'https://example.com/avatar.jpg',
            ],
        ];

        $user = $this->slackUserService->createOrUpdateUser($slackUserData, 'xoxp-token');

        $this->assertEquals('Real Name', $user->name);
    }

    /** @test */
    public function it_handles_missing_avatar_image()
    {
        $slackUserData = [
            'id' => 'U12345',
            'name' => 'Test User',
            'real_name' => 'Test User Real',
            'profile' => [
                'email' => 'test@example.com',
                // image_72 is missing
            ],
        ];

        $user = $this->slackUserService->createOrUpdateUser($slackUserData, 'xoxp-token');

        $this->assertNull($user->avatar_url);
    }

    /** @test */
    public function it_sets_last_login_timestamp()
    {
        $slackUserData = [
            'id' => 'U12345',
            'name' => 'Test User',
            'real_name' => 'Test User Real',
            'profile' => [
                'email' => 'test@example.com',
            ],
        ];

        $user = $this->slackUserService->createOrUpdateUser($slackUserData, 'xoxp-token');

        $this->assertNotNull($user->last_login_at);
        $this->assertTrue($user->last_login_at->isToday());
    }
}