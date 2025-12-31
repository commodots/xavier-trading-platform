<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\KycProfile;
use App\Models\LinkedAccount;
use App\Models\NotificationPreference;
use Illuminate\Support\Str;

class UserSettingsSeeder extends Seeder
{
  public function run(): void
  {
    User::all()->each(function ($user) {

      $user->update([
        'country' => fake()->country(),
      ]);

      $status = fake()->randomElement(['pending', 'approved', 'rejected']);

      $reason = ($status === 'rejected') ? 'Document image was blurry.' : null;

      KycProfile::updateOrCreate(
        ['user_id' => $user->id],
        [
          'level' => fake()->randomElement(['NONE', 'BASIC', 'FULL']),
          'status' => $status, // Use the variable
          'id_type' => fake()->randomElement(['NIN', 'BVN', 'passport']),
          'id_number' => fake()->numerify('###########'),
          'rejection_reason' => $reason,
        ]
      );


      $types = ['bank', 'crypto_wallet'];
      foreach (array_slice($types, 0, rand(1, 2)) as $type) {
        LinkedAccount::create([
          'user_id' => $user->id,
          'type' => $type,
          'provider' => $type === 'bank' ? fake()->company() . ' Bank' : 'Ethereum Network',
          'account_name' => $user->first_name . ' ' . $user->last_name,
          'account_number' => $type === 'bank' ? fake()->bankAccountNumber() : '0x' . Str::random(40),
          'is_verified' => fake()->boolean(),
        ]);
      }


      NotificationPreference::updateOrCreate(
        ['user_id' => $user->id],
        [
          'email' => true,
          'sms' => fake()->boolean(),
          'push' => true,
          'monthly_statements' => true,
          'newsletters' => fake()->boolean(),
        ]
      );
    });
  }
}
