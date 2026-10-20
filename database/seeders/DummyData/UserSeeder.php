<?php

namespace Database\Seeders\DummyData;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::updateOrCreate(
            [
                'email' => 'admin@xavier.com',
            ],

            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'name' => 'System Admin',
                'phone' => '08000000000',
                'role' => 'super-admin',
                'status' => 'active',
                'trading_mode' => 'live',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign super-admin role using Spatie
        if (method_exists($superAdmin, 'syncRoles')) {
            $superAdmin->syncRoles(['super-admin']);
        }

        // 20 stable normal users; repeat runs update rather than append fixtures.
        $demoPassword = Hash::make('password');

        for ($userNumber = 1; $userNumber <= 20; $userNumber++) {
            $email = sprintf('demo-user-%02d@example.invalid', $userNumber);
            $firstName = 'Demo';
            $lastName = "User {$userNumber}";

            User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => "{$firstName} {$lastName}",
                    'email_verified_at' => now(),
                    'password' => $demoPassword,
                    'kyc_status' => 'pending',
                ]
            );
        }
    }
}
