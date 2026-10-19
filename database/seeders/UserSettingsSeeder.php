<?php

namespace Database\Seeders;

use App\Models\KycProfile;
use App\Models\LinkedAccount;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $kycSettings = DB::table('kyc_settings')->get()->keyBy('tier');

        $docs = ['drivers_license', 'intl_passport', 'national_id'];

        User::all()->each(function ($user) use ($kycSettings, $docs) {

            $user->update([
                'country' => fake()->country(),
                'next_of_kin' => fake()->name(),
                'next_of_kin_phone' => fake()->phoneNumber(),
                'next_of_kin_email' => fake()->email(),
            ]);

            $status = fake()->randomElement(['pending', 'verified', 'rejected']);
            $tier = fake()->numberBetween(1, 3);

            $limit = $kycSettings->has($tier) ? $kycSettings[$tier]->daily_limit : 50000;
            $levelName = $kycSettings->has($tier) ? strtolower($kycSettings[$tier]->tier_name) : 'basic';

            $reason = ($status === 'rejected') ? 'Document image was blurry or expired..' : null;

            $choice = $docs[array_rand($docs)];

            $kycData = [
                'tier' => $tier,
                'level' => (string) $levelName,
                'status' => $status,
                'daily_limit' => ($status === 'verified') ? $limit : 0,

                'id_type' => $choice, // Use the random choice
                'id_number' => strtoupper(substr($choice, 0, 3)).$user->id.rand(1000, 9999),
                'bvn' => (fake()->numerify('###########')),
                'nin' => (fake()->numerify('###########')),
                'tin' => (fake()->numerify('##########')),
                'rejection_reason' => $reason,
            ];

            $kycData[$choice] = "kyc/docs/{$choice}_{$user->id}.jpg";

            // If it's an ID card, add the back as well
            if ($choice === 'id_card_front') {
                $kycData['id_card_back'] = "kyc/docs/id_card_back_{$user->id}.jpg";
            }

            // add a sample proof of address in some cases (from first seeder)
            if (rand(0, 1)) {
                $kycData['proof_of_address'] = "kyc/address/poa_{$user->id}.pdf";
            }

            KycProfile::updateOrCreate(['user_id' => $user->id], $kycData);

            foreach (['bank', 'crypto_wallet'] as $type) {
                $accountNumber = $type === 'bank'
                    ? 'DEMO'.str_pad((string) $user->id, 16, '0', STR_PAD_LEFT)
                    : '0x'.str_pad(dechex($user->id), 40, '0', STR_PAD_LEFT);

                LinkedAccount::updateOrCreate(
                    ['user_id' => $user->id, 'type' => $type],
                    [
                        'currency' => $type === 'bank' ? 'NGN' : 'USD',
                        'provider' => $type === 'bank' ? 'Xavier Demo Bank' : 'Ethereum Network',
                        'account_name' => $user->first_name.' '.$user->last_name,
                        'account_number' => $accountNumber,
                        'is_verified' => $user->id % 2 === 0,
                    ]
                );
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
