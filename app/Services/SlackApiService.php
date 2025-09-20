<?php
// app/Services/SlackApiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class SlackApiService
{
    private $baseUrl = 'https://slack.com/api/';

    public function __construct(private string $token) {}

    public function getChannelMessages(string $channelId, ?string $latest = null): array
    {
        $params = [
            'channel' => $channelId,
            'limit' => 100,
        ];
        if ($latest) $params['latest'] = $latest;

        $response = Http::withToken($this->token)
            ->get($this->baseUrl . 'conversations.history', $params);

        return $response->json('messages') ?? [];
    }

    public function getUserInfo(string $userId): array
    {
        $response = Http::withToken($this->token)
            ->get($this->baseUrl . 'users.info', ['user' => $userId]);

        return $response->json('user') ?? [];
    }
}
