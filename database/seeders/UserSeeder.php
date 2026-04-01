<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::updateOrCreate(
            [
                'email' => 'admin@xavier.com',
            ],

            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'name' => 'System Admin',
                'phone' => '08000000000',
                'role' => 'admin',
                'status' => 'active',
                'trading_mode' => 'live',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // 20 normal users
        User::factory()->count(20)->create();
    }
}
