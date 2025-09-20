<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'slack_channel_id' => 'C' . $this->faker->bothify('########'),
            'name' => $this->faker->slug(2),
            'is_private' => false,
            'is_dm' => false,
            'is_mpim' => false,
            'is_archived' => false,
            'member_count' => $this->faker->numberBetween(1, 50),
            'last_synced_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ];
    }

    public function dm(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_dm' => true,
            'is_private' => true,
            'name' => 'dm-' . $this->faker->bothify('user######-user######'),
            'member_count' => 2,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }
}