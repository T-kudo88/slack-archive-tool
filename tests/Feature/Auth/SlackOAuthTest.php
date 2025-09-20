<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use App\Models\User;

class SlackOAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_redirects_to_slack_oauth_with_state_token()
    {
        $response = $this->get('/auth/slack/redirect');

        $response->assertStatus(302);
        $this->assertStringContainsString('https://slack.com/oauth/v2/authorize', $response->headers->get('Location'));
        $this->assertTrue(Session::has('slack_oauth_state'));
    }

    /** @test */
    public function it_includes_required_oauth_parameters_in_redirect()
    {
        $response = $this->get('/auth/slack/redirect');

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('client_id=', $location);
        $this->assertStringContainsString('scope=', $location);
        $this->assertStringContainsString('user_scope=', $location);
        $this->assertStringContainsString('redirect_uri=', $location);
        $this->assertStringContainsString('state=', $location);
    }

    /** @test */
    public function it_creates_new_user_on_successful_callback()
    {
        $state = 'test-state-token';
        Session::put('slack_oauth_state', $state);

        Http::fake([
            'https://slack.com/api/oauth.v2.access' => Http::response([
                'ok' => true,
                'access_token' => 'xoxb-bot-token',
                'authed_user' => [
                    'id' => 'U12345',
                    'access_token' => 'xoxp-user-token',
                ],
            ], 200),

            'https://slack.com/api/users.identity' => Http::response([
                'ok' => true,
                'user' => [
                    'id' => 'U12345',
                    'name' => 'Test User',
                    'real_name' => 'Test User',
                    'profile' => [
                        'email' => 'test@example.com',
                        'image_72' => 'https://example.com/avatar.jpg',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get("/auth/slack/callback?code=test-code&state={$state}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'slack_user_id' => 'U12345',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);
    }

    /** @test */
    public function it_updates_existing_user_with_slack_information()
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'slack_user_id' => null,
        ]);

        $state = 'test-state-token';
        Session::put('slack_oauth_state', $state);

        Http::fake([
            'https://slack.com/api/oauth.v2.access' => Http::response([
                'ok' => true,
                'access_token' => 'xoxb-bot-token',
                'authed_user' => [
                    'id' => 'U12345',
                    'access_token' => 'xoxp-user-token',
                ],
            ], 200),

            'https://slack.com/api/users.identity' => Http::response([
                'ok' => true,
                'user' => [
                    'id' => 'U12345',
                    'name' => 'Updated User',
                    'real_name' => 'Updated User',
                    'profile' => [
                        'email' => 'existing@example.com',
                        'image_72' => 'https://example.com/updated-avatar.jpg',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($existingUser);
        $response = $this->get("/auth/slack/callback?code=test-code&state={$state}");

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'slack_user_id' => 'U12345',
            'name' => 'Updated User',
            'avatar_url' => 'https://example.com/updated-avatar.jpg',
        ]);
    }

    /** @test */
    public function it_rejects_callback_with_invalid_state_token()
    {
        Session::put('slack_oauth_state', 'valid-state');

        $response = $this->get('/auth/slack/callback?code=test-code&state=invalid-state');

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors('slack');
    }

    /** @test */
    public function it_handles_missing_authorization_code()
    {
        $state = 'test-state-token';
        Session::put('slack_oauth_state', $state);

        $response = $this->get("/auth/slack/callback?state={$state}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors('slack');
    }

    /** @test */
    public function it_handles_slack_api_errors_gracefully()
    {
        $state = 'test-state-token';
        Session::put('slack_oauth_state', $state);

        Http::fake([
            'https://slack.com/api/oauth.v2.access' => Http::response([
                'ok' => false,
                'error' => 'invalid_code',
            ], 400),
        ]);

        $response = $this->get("/auth/slack/callback?code=invalid-code&state={$state}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors('slack');
    }

    /** @test */
    public function it_handles_user_info_api_errors()
    {
        $state = 'test-state-token';
        Session::put('slack_oauth_state', $state);

        Http::fake([
            'https://slack.com/api/oauth.v2.access' => Http::response([
                'ok' => true,
                'access_token' => 'xoxb-bot-token',
                'authed_user' => [
                    'id' => 'U12345',
                    'access_token' => 'xoxp-user-token',
                ],
            ], 200),

            'https://slack.com/api/users.identity' => Http::response([
                'ok' => false,
                'error' => 'user_not_found',
            ], 404),
        ]);

        $response = $this->get("/auth/slack/callback?code=test-code&state={$state}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors('slack');
    }

    /** @test */
    public function it_handles_missing_user_token_in_oauth_response()
    {
        $state = 'test-state-token';
        Session::put('slack_oauth_state', $state);

        Http::fake([
            'https://slack.com/api/oauth.v2.access' => Http::response([
                'ok' => true,
                'access_token' => 'xoxb-bot-token',
                // Missing authed_user.access_token
                'authed_user' => [
                    'id' => 'U12345',
                ],
            ], 200),
        ]);

        $response = $this->get("/auth/slack/callback?code=test-code&state={$state}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors('slack');
    }
}
