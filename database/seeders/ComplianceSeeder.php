<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\RiskFlag;
use App\Models\BillingRecord;
use App\Models\UserDevice;
use App\Models\KycProfile;
use App\Models\ActivityLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ComplianceSeeder extends Seeder
{
    /**
     * Seed the database with users demonstrating compliance & security scenarios.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SCENARIO 1 – Users with outstanding debts (wallet_debt > 0)
        |--------------------------------------------------------------------------
        |
        | These users have accumulated debts on their account (failed subscription
        | payments, negative balances from trading losses, unpaid fees, etc.)
        |
        */
        $debtUsers = [
            [
                'email'         => 'debtor1@xavier.com',
                'first_name'    => 'Chioma',
                'last_name'     => 'Okafor',
                'wallet_debt'   => 12500.00,
                'wallet_balance'=> 3200.00,
                'next_fee_due_at' => now()->addDays(5),
                'subscription_status' => 'active',
            ],
            [
                'email'         => 'debtor2@xavier.com',
                'first_name'    => 'Emeka',
                'last_name'     => 'Nwosu',
                'wallet_debt'   => 4750.50,
                'wallet_balance'=> 0.00,
                'next_fee_due_at' => now()->subDays(2), // overdue
                'subscription_status' => 'inactive',
            ],
            [
                'email'         => 'debtor3@xavier.com',
                'first_name'    => 'Folake',
                'last_name'     => 'Balogun',
                'wallet_debt'   => 89000.00,
                'wallet_balance'=> 150.75,
                'next_fee_due_at' => now()->addDay(),
                'subscription_status' => 'active',
            ],
            [
                'email'         => 'debtor4@xavier.com',
                'first_name'    => 'Ibrahim',
                'last_name'     => 'Danjuma',
                'wallet_debt'   => 2500.00,
                'wallet_balance'=> 10000.00,
                'next_fee_due_at' => null,
                'subscription_status' => 'active',
            ],
            [
                'email'         => 'debtor5@xavier.com',
                'first_name'    => 'Tunde',
                'last_name'     => 'Adebayo',
                'wallet_debt'   => 340000.00,
                'wallet_balance'=> 0.00,
                'next_fee_due_at' => now()->subWeeks(3),
                'subscription_status' => 'suspended',
            ],
        ];

        foreach ($debtUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'name'                => $data['first_name'].' '.$data['last_name'],
                    'phone'               => fake()->phoneNumber(),
                    'password'            => Hash::make('password'),
                    'email_verified_at'   => now(),
                    'role'                => 'user',
                    'trading_mode'        => 'live',
                    'wallet_debt'         => $data['wallet_debt'],
                    'wallet_balance'      => $data['wallet_balance'],
                    'subscription_status' => $data['subscription_status'],
                    'next_fee_due_at'     => $data['next_fee_due_at'],
                    'last_active_at'      => now()->subDays(rand(0, 14)),
                ]
            );

            // Create outstanding billing records for debt
            BillingRecord::create([
                'user_id'   => $user->id,
                'amount'    => $data['wallet_debt'],
                'type'      => 'subscription_fee',
                'status'    => 'pending',
                'reference' => 'DEBT-'.strtoupper(Str::random(8)),
            ]);

            // Create NGN wallet with negative-ish scenario
            $this->ensureWallet($user, 'NGN', $data['wallet_balance'], 0);
        }

        /*
        |--------------------------------------------------------------------------
        | SCENARIO 2 – Suspended users with security issues
        |--------------------------------------------------------------------------
        |
        | Users who have been suspended for various reasons: suspicious activity,
        | KYC violations, chargebacks, fraud detection, etc.
        |
        */
        $suspendedUsers = [
            [
                'email'             => 'suspended1@xavier.com',
                'first_name'        => 'Kingsley',
                'last_name'         => 'Ugwu',
                'suspension_reason' => 'Multiple failed KYC attempts with conflicting identity documents',
                'wallet_balance'    => 500000.00,
                'wallet_debt'       => 0,
            ],
            [
                'email'             => 'suspended2@xavier.com',
                'first_name'        => 'Ngozi',
                'last_name'         => 'Eze',
                'suspension_reason' => 'Suspicious withdrawal pattern — repeated requests from unrecognised IP addresses across 3 countries within 2 hours',
                'wallet_balance'    => 12000.00,
                'wallet_debt'       => 3000.00,
            ],
            [
                'email'             => 'suspended3@xavier.com',
                'first_name'        => 'Segun',
                'last_name'         => 'Akinlade',
                'suspension_reason' => 'Chargeback dispute — user disputed legitimate trading fees with issuing bank',
                'wallet_balance'    => 0.00,
                'wallet_debt'       => 15000.00,
            ],
            [
                'email'             => 'suspended4@xavier.com',
                'first_name'        => 'Amara',
                'last_name'         => 'Onyema',
                'suspension_reason' => 'Account takeover suspected — login from anomalous location followed by failed password reset attempts',
                'wallet_balance'    => 78000.00,
                'wallet_debt'       => 0,
            ],
            [
                'email'             => 'suspended5@xavier.com',
                'first_name'        => 'Yusuf',
                'last_name'         => 'Bello',
                'suspension_reason' => 'Violation of terms of service — creation of multiple accounts to exploit referral bonus programme',
                'wallet_balance'    => 2500.00,
                'wallet_debt'       => 0,
            ],
        ];

        foreach ($suspendedUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'name'                => $data['first_name'].' '.$data['last_name'],
                    'phone'               => fake()->phoneNumber(),
                    'password'            => Hash::make('password'),
                    'email_verified_at'   => now(),
                    'role'                => 'user',
                    'trading_mode'        => 'live',
                    'is_suspended'        => true,
                    'suspension_reason'   => $data['suspension_reason'],
                    'wallet_balance'      => $data['wallet_balance'],
                    'wallet_debt'         => $data['wallet_debt'],
                    'subscription_status' => 'suspended',
                    'last_active_at'      => now()->subDays(rand(7, 30)),
                ]
            );

            $this->ensureWallet($user, 'NGN', $data['wallet_balance'], 0);

            // Log the suspension activity
            ActivityLog::create([
                'user_id'    => $user->id,
                'activity'   => 'account_suspended',
                'details'    => ['reason' => $data['suspension_reason']],
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SCENARIO 3 – Users with risk flags (RiskFlag records)
        |--------------------------------------------------------------------------
        |
        | Users flagged by the RiskService for various risky behaviours.
        |
        */
        $riskFlagUsers = [
            [
                'email'       => 'risky1@xavier.com',
                'first_name'  => 'Chinedu',
                'last_name'   => 'Okeke',
                'flags'       => [
                    ['type' => RiskFlag::TYPE_MULTIPLE_KYC_FAILURES, 'severity' => 'high',
                     'meta' => ['attempts' => 4, 'last_provider' => 'Dojah', 'reasons' => ['document_mismatch', 'face_verification_failed', 'liveness_check_failed']]],
                    ['type' => RiskFlag::TYPE_MULTIPLE_DEVICES, 'severity' => 'medium',
                     'meta' => ['device_count' => 5, 'distinct_ips' => 3, 'countries' => ['NG', 'US', 'GH']]],
                ],
            ],
            [
                'email'       => 'risky2@xavier.com',
                'first_name'  => 'Bola',
                'last_name'   => 'Ogunlesi',
                'flags'       => [
                    ['type' => RiskFlag::TYPE_SUSPICIOUS_WITHDRAWAL, 'severity' => 'critical',
                     'meta' => ['attempted_amount' => 2500000, 'currency' => 'NGN', 'ip' => '185.220.101.x', 'country' => 'Unknown', 'time_from_last_login' => '7min']],
                    ['type' => RiskFlag::TYPE_HIGH_DEBT, 'severity' => 'high',
                     'meta' => ['debt_amount' => 175000, 'wallet_balance' => 200, 'debt_to_balance_ratio' => 875]],
                ],
            ],
            [
                'email'       => 'risky3@xavier.com',
                'first_name'  => 'Adaobi',
                'last_name'   => 'Emenike',
                'flags'       => [
                    ['type' => RiskFlag::TYPE_HIGH_DEBT, 'severity' => 'medium',
                     'meta' => ['debt_amount' => 45000, 'wallet_balance' => 5000, 'debt_to_balance_ratio' => 9]],
                    ['type' => RiskFlag::TYPE_MULTIPLE_DEVICES, 'severity' => 'low',
                     'meta' => ['device_count' => 3, 'distinct_ips' => 2, 'countries' => ['NG']]],
                ],
            ],
            [
                'email'       => 'risky4@xavier.com',
                'first_name'  => 'Femi',
                'last_name'   => 'Soyinka',
                'flags'       => [
                    ['type' => RiskFlag::TYPE_MULTIPLE_KYC_FAILURES, 'severity' => 'critical',
                     'meta' => ['attempts' => 7, 'last_provider' => 'QoreId', 'reasons' => ['identity_theft_suspected', 'document_forgery_detected', 'bvn_mismatch']]],
                ],
            ],
            [
                'email'       => 'risky5@xavier.com',
                'first_name'  => 'Zainab',
                'last_name'   => 'Abdullahi',
                'flags'       => [
                    ['type' => RiskFlag::TYPE_SUSPICIOUS_WITHDRAWAL, 'severity' => 'high',
                     'meta' => ['attempted_amount' => 500000, 'currency' => 'NGN', 'ip' => '102.89.23.x', 'country' => 'NG', 'device_id' => 'unknown', 'time_from_last_login' => '1min']],
                ],
            ],
        ];

        foreach ($riskFlagUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'name'                => $data['first_name'].' '.$data['last_name'],
                    'phone'               => fake()->phoneNumber(),
                    'password'            => Hash::make('password'),
                    'email_verified_at'   => now(),
                    'role'                => 'user',
                    'trading_mode'        => 'live',
                    'wallet_debt'         => rand(1000, 100000),
                    'wallet_balance'      => rand(100, 50000),
                    'subscription_status' => 'active',
                    'last_active_at'      => now(),
                ]
            );

            $this->ensureWallet($user, 'NGN', $user->wallet_balance, 0);

            foreach ($data['flags'] as $flag) {
                RiskFlag::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'type'    => $flag['type'],
                    ],
                    [
                        'severity' => $flag['severity'],
                        'meta'     => $flag['meta'],
                    ]
                );
            }

            // Give risky users multiple devices
            UserDevice::create([
                'user_id'       => $user->id,
                'device_name'   => 'Chrome on Windows',
                'ip_address'    => '102.89.'.rand(1, 255).'.'.rand(1, 255),
                'last_active_at'=> now()->subHours(rand(1, 72)),
                'is_trusted'    => false,
            ]);
            UserDevice::create([
                'user_id'       => $user->id,
                'device_name'   => 'Mobile Safari on iOS',
                'ip_address'    => '185.220.'.rand(1, 255).'.'.rand(1, 255),
                'last_active_at'=> now()->subHours(rand(1, 24)),
                'is_trusted'    => false,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SCENARIO 4 – Users with 2FA enabled / high-security posture
        |--------------------------------------------------------------------------
        |
        | Some users who have properly enabled 2FA (good security practice),
        | and some who have it disabled (potential vulnerability).
        |
        */
        $securedUsers = [
            [
                'email'              => 'secure-trader@xavier.com',
                'first_name'         => 'Olawale',
                'last_name'          => 'Fashola',
                'google2fa_enabled'  => true,
                'wallet_balance'     => 2500000.00,
                'wallet_debt'        => 0,
            ],
            [
                'email'              => 'secure-investor@xavier.com',
                'first_name'         => 'Yetunde',
                'last_name'          => 'Alabi',
                'google2fa_enabled'  => true,
                'wallet_balance'     => 15000000.00,
                'wallet_debt'        => 0,
            ],
            [
                'email'              => 'no2fa-trader@xavier.com',
                'first_name'         => 'Bayo',
                'last_name'          => 'Ogunbiyi',
                'google2fa_enabled'  => false,
                'wallet_balance'     => 8750000.00,
                'wallet_debt'        => 12000.00,
            ],
            [
                'email'              => 'no2fa-deposit@xavier.com',
                'first_name'         => 'Chinwe',
                'last_name'          => 'Okoro',
                'google2fa_enabled'  => false,
                'wallet_balance'     => 420000.00,
                'wallet_debt'        => 0,
            ],
        ];

        foreach ($securedUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'name'                => $data['first_name'].' '.$data['last_name'],
                    'phone'               => fake()->phoneNumber(),
                    'password'            => Hash::make('password'),
                    'email_verified_at'   => now(),
                    'role'                => 'user',
                    'trading_mode'        => 'live',
                    'google2fa_enabled'   => $data['google2fa_enabled'],
                    'wallet_balance'      => $data['wallet_balance'],
                    'wallet_debt'         => $data['wallet_debt'],
                    'subscription_status' => 'active',
                    'last_active_at'      => now(),
                ]
            );

            $this->ensureWallet($user, 'NGN', $data['wallet_balance'], 0);
        }

        /*
        |--------------------------------------------------------------------------
        | SCENARIO 5 – Users with unpaid billing records (overdue invoices)
        |--------------------------------------------------------------------------
        |
        | Users who have pending invoices that are past due, to test billing
        | dashboard & dunning logic.
        |
        */
        $billingIssueUsers = [
            ['email' => 'overdue1@xavier.com', 'first_name' => 'Dapo',    'last_name' => 'Adebisi',   'amount' => 15000, 'days_overdue' => 15],
            ['email' => 'overdue2@xavier.com', 'first_name' => 'Kemi',    'last_name' => 'Adebayo',   'amount' => 25000, 'days_overdue' => 30],
            ['email' => 'overdue3@xavier.com', 'first_name' => 'Efosa',   'last_name' => 'Osagie',    'amount' => 5000,  'days_overdue' => 60],
            ['email' => 'overdue4@xavier.com', 'first_name' => 'Halimat', 'last_name' => 'Suleiman',  'amount' => 100000,'days_overdue' => 45],
        ];

        foreach ($billingIssueUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'name'                => $data['first_name'].' '.$data['last_name'],
                    'phone'               => fake()->phoneNumber(),
                    'password'            => Hash::make('password'),
                    'email_verified_at'   => now(),
                    'role'                => 'user',
                    'trading_mode'        => 'live',
                    'wallet_balance'      => rand(0, 5000),
                    'wallet_debt'         => $data['amount'],
                    'subscription_status' => 'inactive',
                    'next_fee_due_at'     => now()->subDays($data['days_overdue']),
                    'last_active_at'      => now()->subDays(rand(5, 20)),
                ]
            );

            $this->ensureWallet($user, 'NGN', $user->wallet_balance, 0);

            // Multiple unpaid billing records
            BillingRecord::create([
                'user_id'   => $user->id,
                'amount'    => $data['amount'],
                'type'      => 'subscription_fee',
                'status'    => 'pending',
                'reference' => 'INV-'.strtoupper(Str::random(10)),
                'created_at'=> now()->subDays($data['days_overdue']),
            ]);

            // Add a second older one
            BillingRecord::create([
                'user_id'   => $user->id,
                'amount'    => $data['amount'] * 0.8,
                'type'      => 'subscription_fee',
                'status'    => 'pending',
                'reference' => 'INV-'.strtoupper(Str::random(10)),
                'created_at'=> now()->subDays($data['days_overdue'] + 30),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SCENARIO 6 – Edge cases
        |--------------------------------------------------------------------------
        |
        | - Unverified email user (never verified)
        | - User with extremely high wallet balance (whale)
        | - Newly registered user with no activity
        | - Dormant user (last active > 90 days ago)
        | - User with negative wallet balance (overdrawn)
        |
        */
        $edgeCaseUsers = [
            // Unverified email
            [
                'email'             => 'unverified@xavier.com',
                'first_name'        => 'Tolu',
                'last_name'         => 'Fashanu',
                'email_verified_at' => null,
                'wallet_balance'    => 0,
                'wallet_debt'       => 0,
                'last_active_at'    => now(),
                'subscription_status' => 'trial',
            ],
            // Whale user
            [
                'email'             => 'whale@xavier.com',
                'first_name'        => 'Micheal',
                'last_name'         => 'Ajayi',
                'email_verified_at' => now(),
                'wallet_balance'    => 95000000.00,
                'wallet_debt'       => 0,
                'last_active_at'    => now()->subHours(2),
                'subscription_status' => 'active',
            ],
            // New user no activity
            [
                'email'             => 'fresh@xavier.com',
                'first_name'        => 'Blessing',
                'last_name'         => 'John',
                'email_verified_at' => now(),
                'wallet_balance'    => 0,
                'wallet_debt'       => 0,
                'last_active_at'    => now(),
                'subscription_status' => 'trial',
            ],
            // Dormant > 90 days
            [
                'email'             => 'dormant@xavier.com',
                'first_name'        => 'Paul',
                'last_name'         => 'Okonkwo',
                'email_verified_at' => now(),
                'wallet_balance'    => 75000.00,
                'wallet_debt'       => 0,
                'last_active_at'    => now()->subDays(120),
                'subscription_status' => 'inactive',
            ],
            // Overdrawn (negative balance scenario via debt exceeding balance)
            [
                'email'             => 'overdrawn@xavier.com',
                'first_name'        => 'Sarah',
                'last_name'         => 'Ibrahim',
                'email_verified_at' => now(),
                'wallet_balance'    => -15000.00,
                'wallet_debt'       => 25000.00,
                'last_active_at'    => now()->subDays(3),
                'subscription_status' => 'inactive',
            ],
        ];

        foreach ($edgeCaseUsers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name'          => $data['first_name'],
                    'last_name'           => $data['last_name'],
                    'name'                => $data['first_name'].' '.$data['last_name'],
                    'phone'               => fake()->phoneNumber(),
                    'password'            => Hash::make('password'),
                    'role'                => 'user',
                    'trading_mode'        => 'live',
                    'email_verified_at'   => $data['email_verified_at'],
                    'wallet_balance'      => $data['wallet_balance'],
                    'wallet_debt'         => $data['wallet_debt'],
                    'last_active_at'      => $data['last_active_at'],
                    'subscription_status' => $data['subscription_status'],
                ]
            );

            $this->ensureWallet($user, 'NGN', max(0, $data['wallet_balance']), 0);
        }
    }

    /**
     * Helper: create an NGN wallet for the user if one doesn't exist.
     */
    private function ensureWallet(User $user, string $currency = 'NGN', float $cleared = 0, float $uncleared = 0): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            [
                'account_number' => 'XAV'.str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'ngn_cleared'    => $currency === 'NGN' ? $cleared : 0,
                'ngn_uncleared'  => $currency === 'NGN' ? $uncleared : 0,
                'usd_cleared'    => $currency === 'USD' ? $cleared : 0,
                'usd_uncleared'  => $currency === 'USD' ? $uncleared : 0,
                'balance'        => $cleared,
                'status'         => 'active',
            ]
        );
    }
}