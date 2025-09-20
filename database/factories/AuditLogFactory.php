<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'admin_user_id' => User::factory(),
            'action' => $this->faker->randomElement(['access_user_message', 'access_dm_channel', 'access_user_data']),
            'resource_type' => $this->faker->randomElement(['message', 'channel', 'user']),
            'resource_id' => $this->faker->numberBetween(1, 1000),
            'accessed_user_id' => User::factory(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'notes' => $this->faker->sentence(),
            'metadata' => [
                'url' => $this->faker->url(),
                'method' => 'GET',
                'headers' => ['Accept' => 'application/json'],
            ],
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}