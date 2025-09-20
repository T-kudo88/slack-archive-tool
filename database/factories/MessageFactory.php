<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'channel_id' => Channel::factory(),
            'user_id' => User::factory(),
            'slack_message_id' => $this->faker->bothify('########.######'),
            'text' => $this->faker->sentence(),
            'thread_ts' => null,
            'timestamp' => $this->faker->numberBetween(1609459200, time()), // 2021-2024
            'reply_count' => 0,
            'message_type' => 'message',
            'has_files' => false,
            'reactions' => null,
            'metadata' => null,
        ];
    }

    public function withReactions(): static
    {
        return $this->state(fn (array $attributes) => [
            'reactions' => [
                ['name' => 'thumbsup', 'count' => $this->faker->numberBetween(1, 5)],
                ['name' => 'heart', 'count' => $this->faker->numberBetween(1, 3)],
            ],
        ]);
    }

    public function withFiles(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_files' => true,
            'metadata' => [
                'files' => [
                    [
                        'id' => 'F' . $this->faker->bothify('########'),
                        'name' => $this->faker->word() . '.pdf',
                        'mimetype' => 'application/pdf',
                    ]
                ]
            ],
        ]);
    }

    public function thread(): static
    {
        return $this->state(fn (array $attributes) => [
            'thread_ts' => $this->faker->bothify('########.######'),
            'reply_count' => $this->faker->numberBetween(1, 10),
        ]);
    }
}