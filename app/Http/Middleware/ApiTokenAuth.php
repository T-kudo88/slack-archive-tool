<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bearerトークンまたはapi_tokenパラメータからトークンを取得
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json([
                'error' => 'API token required',
                'message' => 'Please provide API token via Authorization header (Bearer token) or api_token parameter'
            ], 401);
        }

        // トークンでユーザーを検索
        $user = User::where('api_token', $token)->first();

        if (!$user) {
            return response()->json([
                'error' => 'Invalid API token',
                'message' => 'The provided API token is invalid'
            ], 401);
        }

        // ユーザーがアクティブかチェック
        if (!$user->is_active) {
            return response()->json([
                'error' => 'Account inactive',
                'message' => 'Your account is inactive'
            ], 403);
        }

        // トークンの有効期限をチェック
        if ($user->isApiTokenExpired()) {
            return response()->json([
                'error' => 'Token expired',
                'message' => 'Your API token has expired. Please generate a new one.'
            ], 401);
        }

        // ユーザーをログイン状態にする
        Auth::setUser($user);
        
        // APIトークン最終使用時刻を更新（非同期で）
        dispatch(function () use ($user) {
            $user->updateApiTokenLastUsed();
        })->afterResponse();

        return $next($request);
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

        // 2. api_tokenパラメータから取得（GET/POST両対応）
        $token = $request->input('api_token');
        if ($token) {
            return $token;
        }

        // 3. X-API-TOKENヘッダーから取得
        return $request->header('X-API-TOKEN');
    }
}
