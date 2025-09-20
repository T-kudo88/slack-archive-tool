<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'slack_team_id' => 'T' . $this->faker->bothify('########'),
            'name' => $this->faker->company(),
            'domain' => $this->faker->domainWord(),
            'bot_token' => 'xoxb-' . $this->faker->bothify('###-###-###-###'),
            'is_active' => true,
        ];
    }
}