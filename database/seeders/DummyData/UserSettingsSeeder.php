<?php

namespace Database\Seeders\DummyData;

use App\Models\KycProfile;
use App\Models\LinkedAccount;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $kycSettings = DB::table('kyc_settings')->get()->keyBy('tier');

        $docs = ['drivers_license', 'intl_passport', 'national_id'];

        User::all()->each(function ($user) use ($kycSettings, $docs) {

            $user->update([
                'country' => ['Nigeria', 'Ghana', 'Kenya'][$user->id % 3],
                'next_of_kin' => "Demo Contact {$user->id}",
                'next_of_kin_phone' => '+234800'.str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
                'next_of_kin_email' => "demo-contact-{$user->id}@example.invalid",
            ]);

            $status = ['pending', 'verified', 'rejected'][$user->id % 3];
            $tier = ($user->id % 3) + 1;

            $limit = $kycSettings->has($tier) ? $kycSettings[$tier]->daily_limit : 50000;
            $levelName = $kycSettings->has($tier) ? strtolower($kycSettings[$tier]->tier_name) : 'basic';

            $reason = ($status === 'rejected') ? 'Document image was blurry or expired..' : null;

            $choice = $docs[$user->id % count($docs)];

            $kycData = [
                'tier' => $tier,
                'level' => (string) $levelName,
                'status' => $status,
                'daily_limit' => ($status === 'verified') ? $limit : 0,

                'id_type' => $choice, // Use the random choice
                'id_number' => strtoupper(substr($choice, 0, 3)).str_pad((string) $user->id, 10, '0', STR_PAD_LEFT),
                'bvn' => str_pad((string) (10000000000 + $user->id), 11, '0', STR_PAD_LEFT),
                'nin' => str_pad((string) (20000000000 + $user->id), 11, '0', STR_PAD_LEFT),
                'tin' => str_pad((string) (3000000000 + $user->id), 10, '0', STR_PAD_LEFT),
                'rejection_reason' => $reason,
            ];

            $kycData[$choice] = "kyc/docs/{$choice}_{$user->id}.jpg";

            // If it's an ID card, add the back as well
            if ($choice === 'id_card_front') {
                $kycData['id_card_back'] = "kyc/docs/id_card_back_{$user->id}.jpg";
            }

            // add a sample proof of address in some cases (from first seeder)
            if ($user->id % 2 === 0) {
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
                    'sms' => $user->id % 2 === 0,
                    'push' => true,
                    'monthly_statements' => true,
                    'newsletters' => $user->id % 3 === 0,
                ]
            );
        });
    }
}
