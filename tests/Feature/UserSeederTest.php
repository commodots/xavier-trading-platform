<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_the_user_seeder_twice_does_not_duplicate_users(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(UserSeeder::class);
        $userCountAfterFirstRun = User::query()->count();

        $this->seed(UserSeeder::class);

        $this->assertSame($userCountAfterFirstRun, User::query()->count());
    }
}