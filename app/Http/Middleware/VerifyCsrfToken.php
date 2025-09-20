<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * CSRFチェックから除外するURI
     *
     * @var array<int, string>
     */
    protected $except = [
        // Slack OAuth callback
        '/auth/slack/callback',
        
        // APIエンドポイント（APIトークン認証を使用）
        '/api/*',
        
        // Webhookエンドポイント
        '/webhooks/*',
    ];

    /**
     * APIトークンが提供されている場合はCSRF検証をスキップ
     */
    protected function shouldPassThrough($request)
    {
        // 親クラスの除外チェック
        if (parent::shouldPassThrough($request)) {
            return true;
        }

        // APIトークンが提供されている場合はCSRF検証をスキップ
        return $this->hasApiToken($request);
    }

    /**
     * APIトークンが提供されているかチェック
     */
    private function hasApiToken($request): bool
    {
        // Authorization: Bearer tokenヘッダー
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return true;
        }

        // api_tokenパラメータ
        if ($request->input('api_token')) {
            return true;
        }

        // X-API-TOKENヘッダー
        if ($request->header('X-API-TOKEN')) {
            return true;
        }

        return false;
    }

    /**
     * APIリクエストかどうか判定
     */
    private function isApiRequest($request): bool
    {
        // User-Agentでcurl, Postman等を検出
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
