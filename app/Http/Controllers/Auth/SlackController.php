<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SlackUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
            // Validate state
            $sessionState = Session::get('slack_oauth_state');
            $requestState = $request->get('state');
            if (!$request->has('state') || $requestState !== $sessionState) {
                return redirect()->route('login')->withErrors([
                    'slack' => 'Slack認証のセキュリティ検証に失敗しました。'
                ]);
            }

            // Exchange code for token
            $tokenResponse = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
                'client_id'     => config('services.slack.client_id'),
                'client_secret' => config('services.slack.client_secret'),
                'code'          => $request->code,
                'redirect_uri'  => route('slack.callback'),
            ]);
            $tokenData = $tokenResponse->json();

            if (!($tokenData['ok'] ?? false)) {
                return redirect()->route('login')->withErrors([
                    'slack' => 'Slackからのアクセストークン取得に失敗しました。'
                ]);
            }

            // Get user info
            $userToken = $tokenData['authed_user']['access_token'] ?? null;
            if (!$userToken) {
                return redirect()->route('login')->withErrors([
                    'slack' => 'ユーザートークンの取得に失敗しました。'
                ]);
            }

            $userResponse = Http::withToken($userToken)->get('https://slack.com/api/users.identity');
            $userInfo = $userResponse->json();
            if (!($userInfo['ok'] ?? false) || !isset($userInfo['user'])) {
                return redirect()->route('login')->withErrors([
                    'slack' => 'Slackからのユーザー情報取得に失敗しました。'
                ]);
            }

            // Create or update user
            $user = $this->slackUserService->createOrUpdateUser(
                $userInfo['user'],
                $userToken
            );

            Session::forget('slack_oauth_state');
            Auth::login($user, true);
            $request->session()->save();

            if (!Auth::check()) {
                return redirect()->route('login')->withErrors([
                    'slack' => 'ログインに失敗しました。再度お試しください。'
                ]);
            }

            // ✅ 管理者と一般ユーザーで分岐
            // ✅ 管理者と一般ユーザーで分岐
            if ($user->is_admin) {
                Log::info('Admin user logged in', ['user_id' => $user->id]);
                return redirect()->intended(route('admin.dashboard'))
                    ->with('success', '管理者としてログインしました 🚀');
            } else {
                Log::info('Regular user logged in', ['user_id' => $user->id]);
                return redirect()->intended(route('dashboard'))
                    ->with('success', 'Slackアカウントと正常に連携されました！');
            }
        } catch (\Exception $e) {
            Log::error('Slack OAuth callback exception', [
                'message' => $e->getMessage(),
            ]);
            return redirect()->route('login')->withErrors([
                'slack' => 'Slack連携中にエラーが発生しました。'
            ]);
        }
    }
}
