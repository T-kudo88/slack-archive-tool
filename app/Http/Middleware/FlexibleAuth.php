<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FlexibleAuth
{
    /**
     * Handle an incoming request - ブラウザセッション認証またはAPIトークン認証
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. まず通常のセッション認証をチェック
        if (Auth::check()) {
            return $next($request);
        }

        // 2. APIトークンによる認証を試行
        $token = $this->extractToken($request);
        if ($token) {
            $user = User::where('api_token', $token)->first();

            if ($user && $user->is_active && !$user->isApiTokenExpired()) {
                Auth::setUser($user);
                
                // APIトークン最終使用時刻を更新（非同期で）
                dispatch(function () use ($user) {
                    $user->updateApiTokenLastUsed();
                })->afterResponse();

                return $next($request);
            }
        }

        // 3. 認証失敗時の処理
        return $this->handleUnauthenticated($request);
    }

    /**
     * 認証されていない場合の処理
     */
    private function handleUnauthenticated(Request $request): Response
    {
        // JSON APIリクエストの場合
        if ($request->expectsJson() || $this->isApiRequest($request)) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Please provide valid authentication credentials'
            ], 401);
        }

        // ブラウザリクエストの場合はログインページにリダイレクト
        return redirect()->route('login');
    }

    /**
     * リクエストからAPIトークンを抽出
     */
    private function extractToken(Request $request): ?string
    {
        // 1. Authorization: Bearer tokenヘッダーから取得
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // 2. api_tokenパラメータから取得
        $token = $request->input('api_token');
        if ($token) {
            return $token;
        }

        // 3. X-API-TOKENヘッダーから取得
        return $request->header('X-API-TOKEN');
    }

    /**
     * APIリクエストかどうか判定
     */
    private function isApiRequest(Request $request): bool
    {
        // APIトークンが提供されている
        if ($this->extractToken($request)) {
            return true;
        }

        // User-Agentでcurl, Postman, HTTPieなどを検出
        $userAgent = $request->header('User-Agent', '');
        $apiUserAgents = ['curl', 'Postman', 'HTTPie', 'Insomnia', 'Thunder Client'];
        
        foreach ($apiUserAgents as $agent) {
            if (str_contains(strtolower($userAgent), strtolower($agent))) {
                return true;
            }
        }

        // Accept ヘッダーでJSON優先を検出
        $acceptHeader = $request->header('Accept', '');
        if (str_contains($acceptHeader, 'application/json') && 
            !str_contains($acceptHeader, 'text/html')) {
            return true;
        }

        return false;
    }
}
