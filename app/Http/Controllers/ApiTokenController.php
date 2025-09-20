<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * APIトークン管理画面表示
     */
    public function index(): Response
    {
        $user = Auth::user();
        
        return Inertia::render('ApiTokens/Index', [
            'hasToken' => !empty($user->api_token),
            'tokenCreatedAt' => $user->api_token_created_at?->format('Y-m-d H:i:s'),
            'tokenLastUsed' => $user->api_token_last_used_at?->format('Y-m-d H:i:s'),
            'isExpired' => $user->isApiTokenExpired(),
        ]);
    }

    /**
     * APIトークンを生成
     */
    public function generate(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // 既存トークンがある場合は確認を要求
        if ($user->api_token && !$request->boolean('force')) {
            return response()->json([
                'error' => 'Token exists',
                'message' => 'APIトークンが既に存在します。上書きする場合はforce=trueを指定してください。',
                'requires_confirmation' => true,
            ], 409);
        }

        $token = $user->generateApiToken();

        return response()->json([
            'success' => true,
            'message' => 'APIトークンを生成しました',
            'token' => $token,
            'created_at' => $user->api_token_created_at->format('Y-m-d H:i:s'),
            'warning' => 'このトークンは今回のみ表示されます。必ず安全な場所に保存してください。',
        ]);
    }

    /**
     * APIトークンを再生成
     */
    public function regenerate(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->api_token) {
            return response()->json([
                'error' => 'No token exists',
                'message' => 'APIトークンが存在しません。まずトークンを生成してください。',
            ], 404);
        }

        $token = $user->regenerateApiToken();

        return response()->json([
            'success' => true,
            'message' => 'APIトークンを再生成しました',
            'token' => $token,
            'created_at' => $user->api_token_created_at->format('Y-m-d H:i:s'),
            'warning' => 'このトークンは今回のみ表示されます。必ず安全な場所に保存してください。',
        ]);
    }

    /**
     * APIトークンを削除
     */
    public function revoke(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->api_token) {
            return response()->json([
                'error' => 'No token exists',
                'message' => 'APIトークンが存在しません。',
            ], 404);
        }

        $user->revokeApiToken();

        return response()->json([
            'success' => true,
            'message' => 'APIトークンを削除しました',
        ]);
    }

    /**
     * APIトークン情報を取得
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'has_token' => !empty($user->api_token),
            'created_at' => $user->api_token_created_at?->format('Y-m-d H:i:s'),
            'last_used_at' => $user->api_token_last_used_at?->format('Y-m-d H:i:s'),
            'is_expired' => $user->isApiTokenExpired(),
            'expires_in_days' => $user->api_token_created_at ? 
                max(0, 90 - $user->api_token_created_at->diffInDays(now())) : 0,
        ]);
    }

    /**
     * APIトークンテスト
     */
    public function test(Request $request): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'message' => 'APIトークン認証に成功しました',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'is_active' => $user->is_active,
            ],
            'authenticated_via' => $request->hasHeader('Authorization') ? 'bearer_token' : 
                ($request->input('api_token') ? 'parameter' : 
                ($request->hasHeader('X-API-TOKEN') ? 'header' : 'session')),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * API使用例を表示
     */
    public function examples(): JsonResponse
    {
        return response()->json([
            'examples' => [
                'curl_bearer' => [
                    'description' => 'Authorization: Bearer ヘッダーを使用',
                    'example' => 'curl -H "Authorization: Bearer YOUR_API_TOKEN" ' . url('/api/test'),
                ],
                'curl_parameter' => [
                    'description' => 'URLパラメータとしてトークンを送信',
                    'example' => 'curl "' . url('/api/test') . '?api_token=YOUR_API_TOKEN"',
                ],
                'curl_header' => [
                    'description' => 'X-API-TOKENヘッダーを使用',
                    'example' => 'curl -H "X-API-TOKEN: YOUR_API_TOKEN" ' . url('/api/test'),
                ],
                'postman' => [
                    'description' => 'Postmanでの設定',
                    'steps' => [
                        '1. Authorization タブを選択',
                        '2. Type を "Bearer Token" に設定',
                        '3. Token フィールドにAPIトークンを入力',
                    ],
                ],
            ],
            'endpoints' => [
                'GET /messages' => 'メッセージ一覧取得',
                'GET /messages/search' => 'メッセージ検索',
                'GET /messages/{id}' => '特定メッセージ取得',
                'GET /files' => 'ファイル一覧取得',
                'POST /api/test' => 'APIトークンテスト',
            ],
        ]);
    }
}
