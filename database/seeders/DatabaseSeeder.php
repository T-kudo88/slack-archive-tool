<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'id'         => 'U12345678', // SlackのユーザーID（例）
            'name'       => 'Test User',
            'email'      => 'test@example.com',
            'avatar_url' => null,
            'is_admin'   => true,
            'is_active'  => true,
        ]);
    }
}
