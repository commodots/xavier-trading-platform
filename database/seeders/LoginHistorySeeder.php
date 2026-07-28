<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoginHistory;
use App\Models\User;

class LoginHistorySeeder extends Seeder
{
    public function run(): void
    {
        $users = User::take(10)->get();
        
        foreach ($users as $user) {
            // Create successful logins
            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => '192.168.1.' . rand(1, 255),
                'device' => 'Chrome on Windows',
                'browser' => 'Chrome 120.0',
                'platform' => 'Windows 10',
                'location' => 'Lagos, Nigeria',
                'successful' => true,
                'logged_in_at' => now()->subDays(rand(1, 30)),
            ]);
            
            // Occasionally create failed login attempts
            if (rand(0, 1)) {
                LoginHistory::create([
                    'user_id' => $user->id,
                    'ip_address' => '192.168.1.' . rand(1, 255),
                    'device' => 'Unknown',
                    'browser' => 'Firefox 121.0',
                    'platform' => 'Linux',
                    'location' => 'Unknown',
                    'successful' => false,
                    'logged_in_at' => now()->subDays(rand(1, 7)),
                ]);
            }
        }
    }
}