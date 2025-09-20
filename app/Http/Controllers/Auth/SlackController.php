<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SlackUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

class SlackController extends Controller
{
    protected SlackUserService $slackUserService;

    public function __construct(SlackUserService $slackUserService)
    {
        $this->slackUserService = $slackUserService;
    }

    /**
     * Redirect to Slack OAuth
     */
    public function redirect()
    {
        $state = bin2hex(random_bytes(16));
        session(['slack_oauth_state' => $state]);

        $query = http_build_query([
            'client_id'     => config('services.slack.client_id'),
            'scope'         => 'channels:history,channels:read,files:read,groups:history,groups:read,im:history,im:read,mpim:history,mpim:read,reactions:read,users:read',
            'user_scope'    => 'identity.basic,identity.email,identity.avatar',
            'redirect_uri'  => route('slack.callback'),
            'state'         => $state,
        ]);

        return redirect('https://slack.com/oauth/v2/authorize?' . $query);
    }

    /**
     * Handle Slack OAuth callback
     */
    public function callback(Request $request)
    {
        Log::info('Slack OAuth callback started', [
            'request_params' => $request->all(),
            'session_state' => Session::get('slack_oauth_state'),
            'has_state' => $request->has('state'),
            'has_code' => $request->has('code'),
        ]);

        try {
            // Validate state parameter for CSRF protection
            $sessionState = Session::get('slack_oauth_state');
            $requestState = $request->get('state');

            if (!$request->has('state') || $requestState !== $sessionState) {
                Log::warning('Slack OAuth state validation failed', [
                    'session_state' => $sessionState,
                    'request_state' => $requestState,
                    'state_match' => $requestState === $sessionState,
                ]);

                return redirect()->route('dashboard')->withErrors([
                    'slack' => 'Slack認証のセキュリティ検証に失敗しました。再度お試しください。'
                ]);
            }

            // Check if authorization code is provided
            if (!$request->has('code')) {
                Log::warning('Slack OAuth code not provided');
                return redirect()->route('dashboard')->withErrors([
                    'slack' => '認証コードが提供されませんでした。'
                ]);
            }

            Log::info('Exchanging authorization code for access token');

            // Exchange authorization code for access token
            $tokenResponse = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
                'client_id'     => config('services.slack.client_id'),
                'client_secret' => config('services.slack.client_secret'),
                'code'          => $request->code,
                'redirect_uri'  => route('slack.callback'),
            ]);

            $tokenData = $tokenResponse->json();

            Log::info('Token exchange response', [
                'status' => $tokenResponse->status(),
                'ok' => $tokenData['ok'] ?? 'not_set',
                'error' => $tokenData['error'] ?? 'none',
            ]);

            if (!isset($tokenData['ok']) || !$tokenData['ok']) {
                Log::error('Failed to exchange authorization code', [
                    'response' => $tokenData
                ]);
                return redirect()->route('dashboard')->withErrors([
                    'slack' => 'Slackからのアクセストークン取得に失敗しました。'
                ]);
            }

            Log::info('Getting user information from Slack');

            // Get user token from OAuth response (not bot token)
            $userToken = $tokenData['authed_user']['access_token'] ?? null;
            if (!$userToken) {
                Log::error('User token not found in OAuth response', [
                    'response' => $tokenData
                ]);
                return redirect()->route('dashboard')->withErrors([
                    'slack' => 'ユーザートークンの取得に失敗しました。'
                ]);
            }

            // Get user information from Slack using User Token
            $userResponse = Http::withToken($userToken)
                ->get('https://slack.com/api/users.identity');

            $userInfo = $userResponse->json();

            Log::info('User info response', [
                'status' => $userResponse->status(),
                'ok' => $userInfo['ok'] ?? 'not_set',
                'has_user' => isset($userInfo['user']),
                'error' => $userInfo['error'] ?? 'none',
            ]);

            if (!isset($userInfo['ok']) || !$userInfo['ok'] || !isset($userInfo['user'])) {
                Log::error('Failed to retrieve user information', [
                    'response' => $userInfo
                ]);
                return redirect()->route('dashboard')->withErrors([
                    'slack' => 'Slackからのユーザー情報取得に失敗しました。'
                ]);
            }

            Log::info('Creating or updating user', [
                'slack_user_id' => $userInfo['user']['id'] ?? 'unknown'
            ]);

            // Create or update user using the service
            $user = $this->slackUserService->createOrUpdateUser(
                $userInfo['user'],
                $userToken
            );

            // Clear the state from session before login
            Session::forget('slack_oauth_state');

            // Store intended URL before regenerating session
            $intendedUrl = Session::pull('url.intended', route('dashboard'));

            // Log the user in and remember them
            Auth::login($user, true);

            // Force session save to ensure authentication persists
            $request->session()->save();

            // Log authentication status
            Log::info('User login attempt', [
                'user_id' => $user->id,
                'is_authenticated' => Auth::check(),
                'session_id' => $request->session()->getId(),
            ]);

            // Ensure the user is properly authenticated
            if (!Auth::check()) {
                Log::error('User authentication failed after login', [
                    'user_id' => $user->id,
                    'session_id' => $request->session()->getId(),
                ]);
                return redirect()->route('login')->withErrors([
                    'slack' => 'ログインに失敗しました。再度お試しください。'
                ]);
            }

            Log::info('Slack OAuth completed successfully', [
                'user_id' => $user->id,
                'slack_user_id' => $user->slack_user_id,
                'intended_url' => $intendedUrl,
                'session_id' => $request->session()->getId(),
                'final_auth_check' => Auth::check(),
            ]);

            return redirect($intendedUrl)->with('success', 'Slackアカウントと正常に連携されました！');
        } catch (\Exception $e) {
            Log::error('Slack OAuth callback exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('dashboard')->withErrors([
                'slack' => 'Slack連携中にエラーが発生しました。再度お試しください。'
            ]);
        }
    }
}
