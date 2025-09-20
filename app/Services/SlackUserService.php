<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class SlackUserService
{
    /**
     * Create or update user from Slack user data
     */
    public function createOrUpdateUser(array $slackUserData, string $accessToken): User
    {
        $slackUserId = $slackUserData['id'];
        $email = $slackUserData['profile']['email'] ?? null;

        // Generate email if not provided
        if (!$email) {
            $email = $slackUserId . '@slack.local';
        }

        // SlackユーザーIDを主キー(id)として検索
        $user = User::find($slackUserId);

        // メールアドレスでの検索もフォールバックとして許可
        if (!$user && $email !== $slackUserId . '@slack.local') {
            $user = User::where('email', $email)->first();
        }

        // 更新・作成用データ
        $userData = [
            'id'         => $slackUserId, // 主キーがSlackユーザーID
            'name'       => $this->getUserName($slackUserData),
            'email'      => $email,
            'avatar_url' => $slackUserData['profile']['image_72'] ?? null,
            'is_active'  => true,
            'is_admin'   => false,
            'updated_at' => Carbon::now(),
        ];

        if ($user) {
            $user->update($userData);
        } else {
            $userData['created_at'] = Carbon::now();
            $user = User::create($userData);
        }

        return $user;
    }

    /**
     * Get user name from Slack data, preferring real_name over name
     */
    private function getUserName(array $slackUserData): string
    {
        if (!empty($slackUserData['real_name'])) {
            return $slackUserData['real_name'];
        }

        return $slackUserData['name'] ?? 'Unknown User';
    }
}
