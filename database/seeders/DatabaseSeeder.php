<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin Active Barn',
            'email' => 'admin@activebarn.com',
            'password' => bcrypt('password123'),
        ]);
    }
}
