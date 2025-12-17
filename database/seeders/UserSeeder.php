<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'first_name' => 'System',
            'last_name' => 'Admin',
            'email' => 'admin@xavier.com',
            'phone' => '08000000000',
            'role' => 'admin',
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        // 20 normal users
        User::factory()->count(20)->create();
    }
}
