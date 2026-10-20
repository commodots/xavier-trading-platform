<?php

namespace Database\Seeders\Production;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $email = config('production.system_admin.email');
        $password = config('production.system_admin.password');
        $name = trim((string) config('production.system_admin.name', 'System Administrator'));

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('A valid SYSTEM_ADMIN_EMAIL is required to seed the production system admin.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            if (! is_string($password) || strlen($password) < 8) {
                throw new RuntimeException('SYSTEM_ADMIN_PASSWORD must contain at least 8 characters for the initial admin account.');
            }

            [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');

            $user->forceFill([
                'name' => $name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'role' => 'super-admin',
                'trading_mode' => 'live',
            ])->save();
        }

        $user->syncRoles(['admin', 'super-admin']);
    }
}