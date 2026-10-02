<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Fee;
use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProduct;
use App\Models\FixedIncomeTransaction;
use App\Models\FxConversion;
use App\Models\Kyc;
use App\Models\KycProfile;
use App\Models\KycVerification;
use App\Models\Ledger;
use App\Models\LinkedAccount;
use App\Models\LoginHistory;
use App\Models\NewTransaction;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderAccount;
use App\Models\ProviderSyncLog;
use App\Models\ReportExport;
use App\Models\ReportHistory;
use App\Models\ReportSchedule;
use App\Models\ReportTemplate;
use App\Models\RevenueRecord;
use App\Models\RiskFlag;
use App\Models\SubscriptionPlan;
use App\Models\Symbol;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\TransactionAudit;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserSubscription;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Watchlist;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Comprehensive, deterministic demo/UAT dataset.
 *
 * IMPORTANT:
 * - Run this explicitly with --class=DemoShowcaseSeeder.
 * - It is intentionally NOT called by DatabaseSeeder so fake financial data
 *   cannot accidentally be inserted into production.
 * - Existing configuration seeders can still be run before this seeder.
 */
class DemoShowcaseSeeder extends Seeder
{
    private array $users = [];

    private array $wallets = [];

    private array $orders = [];

    private array $trades = [];

    private array $newTransactions = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedUsers();
            $this->seedKyc();
            $this->seedWallets();
            $this->seedTransactionTypesAndCharges();
            $this->seedFundingAndTransactions();
            $this->seedSecuritiesAndMarkets();
            $this->seedOrdersTradesSettlements();
            $this->seedPortfoliosAndPositions();
            $this->seedFixedIncome();
            $this->seedFx();
            $this->seedBankingAndWithdrawals();
            $this->seedSubscriptionsAndBilling();
            $this->seedExpenses();
            $this->seedReportsAndAnalytics();
            $this->seedNotificationsAndAudit();
            $this->seedProviderAndCslData();
        });

        $this->command?->info('Xavier demo/UAT showcase data seeded successfully.');
        $this->command?->info('Demo login password for named users: password');
    }

    private function seedUsers(): void
    {
        $people = [
            ['key' => 'bade', 'first' => 'Bade', 'last' => 'Ayoade', 'email' => 'bade.ayoade@demo.xavier.local', 'kyc' => 'verified', 'level' => 3, 'subscription' => 'active'],
            ['key' => 'olutoyin', 'first' => 'Olutoyin', 'last' => 'Ayoade', 'email' => 'olutoyin.ayoade@demo.xavier.local', 'kyc' => 'verified', 'level' => 2, 'subscription' => 'active'],
            ['key' => 'naomi', 'first' => 'Naomi', 'last' => 'Okelue', 'email' => 'naomi.okelue@demo.xavier.local', 'kyc' => 'pending', 'level' => 1, 'subscription' => 'trial'],
            ['key' => 'benjamin', 'first' => 'Benjamin', 'last' => 'Osikomaiya', 'email' => 'benjamin.osikomaiya@demo.xavier.local', 'kyc' => 'verified', 'level' => 3, 'subscription' => 'active'],
            ['key' => 'bola', 'first' => 'Bola', 'last' => 'Ahmed', 'email' => 'bola.ahmed@demo.xavier.local', 'kyc' => 'rejected', 'level' => 0, 'subscription' => 'inactive'],
            ['key' => 'waidi', 'first' => 'Waidi', 'last' => 'Shagari', 'email' => 'waidi.shagari@demo.xavier.local', 'kyc' => 'verified', 'level' => 2, 'subscription' => 'active'],
        ];

        $admin = User::updateOrCreate(
            ['email' => 'demo.admin@xavier.local'],
            [
                'first_name' => 'Demo',
                'last_name' => 'Administrator',
                'name' => 'Demo Administrator',
                'phone' => '08000000001',
                'role' => 'super-admin',
                'status' => 'active',
                'trading_mode' => 'live',
                'subscription_status' => 'active',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        DB::table('users')->where('id', $admin->id)->update([
            'role' => 'super-admin',
            'status' => 'active',
            'verification_level' => 3,
        ]);
        if (method_exists($admin, 'syncRoles') && DB::table('roles')->where('name', 'super-admin')->exists()) {
            $admin->syncRoles(['super-admin']);
        }
        $this->users['admin'] = $admin->fresh();

        foreach ($people as $index => $person) {
            $user = User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'first_name' => $person['first'],
                    'last_name' => $person['last'],
                    'name' => $person['first'].' '.$person['last'],
                    'phone' => '080'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                    'role' => 'user',
                    'status' => 'active',
                    'trading_mode' => 'live',
                    'subscription_status' => $person['subscription'],
                    'kyc_status' => $person['kyc'],
                    'verification_level' => $person['level'],
                    'email_verified_at' => now()->subDays($index + 1),
                    'password' => Hash::make('password'),
                    'country' => 'Nigeria',
                    'address' => 'Lagos, Nigeria',
                    'gender' => $index % 2 === 0 ? 'male' : 'female',
                    'dob' => Carbon::create(1985 + $index, 2 + $index, 10 + $index),
                    'next_of_kin' => $index === 0 ? 'Demo Next of Kin' : 'Family Contact',
                    'next_of_kin_phone' => '081'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                    'next_of_kin_email' => 'nextofkin'.$index.'@demo.xavier.local',
                    'last_active_at' => now()->subHours($index * 3),
                    'next_billing_date' => now()->addDays(20 + $index)->toDateString(),
                ]
            );

            DB::table('users')->where('id', $user->id)->update([
                'role' => 'user',
                'status' => 'active',
                'verification_level' => $person['level'],
            ]);
            $this->users[$person['key']] = $user->fresh();
        }
    }

    private function seedKyc(): void
    {
        foreach ($this->users as $key => $user) {
            if ($key === 'admin') {
                continue;
            }

            $status = match ($user->kyc_status) {
                'verified' => 'verified',
                'rejected' => 'rejected',
                default => 'pending',
            };

            // Legacy/simple KYC table used by older flows.
            Kyc::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'provider' => 'dojah',
                    'id_type' => 'NIN',
                    'id_value' => 'DEM'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                    'bvn' => '220000000'.str_pad((string) $user->id, 2, '0', STR_PAD_LEFT),
                    'nin' => '900000000'.str_pad((string) $user->id, 2, '0', STR_PAD_LEFT),
                    'status' => $status,
                ]
            );

            // Current KYC profile used by User::kyc() and withdrawal/KYC checks.
            KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'level' => match ((int) $user->verification_level) {
                        3 => 'full',
                        2 => 'basic',
                        1 => 'basic',
                        default => 'none',
                    },
                    'tier' => (int) $user->verification_level,
                    'daily_limit' => match ((int) $user->verification_level) {
                        3 => 5000000,
                        2 => 1000000,
                        1 => 250000,
                        default => 0,
                    },
                    'bvn' => '220000000'.str_pad((string) $user->id, 2, '0', STR_PAD_LEFT),
                    'nin' => '900000000'.str_pad((string) $user->id, 2, '0', STR_PAD_LEFT),
                    'tin' => 'TIN'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                    'id_type' => 'NIN',
                    'id_number' => 'DEM'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                    'status' => $status === 'verified' ? 'approved' : $status,
                    'rejection_reason' => $status === 'rejected' ? 'Demo rejected profile for workflow testing.' : null,
                    'verified_at' => $status === 'verified' ? now()->subDays(10) : null,
                    'meta' => ['demo' => true, 'verification_provider' => 'dojah'],
                ]
            );

            KycVerification::updateOrCreate(
                ['user_id' => $user->id, 'verification_type' => 'identity'],
                [
                    'verification_id' => 'DOJAH-DEMO-'.$user->id,
                    'status' => $status === 'verified' ? 'approved' : $status,
                    'response_json' => ['demo' => true, 'provider' => 'dojah'],
                ]
            );
        }
    }

    private function seedWallets(): void
    {
        $balances = [
            'bade' => [6500000, 4200],
            'olutoyin' => [3200000, 1800],
            'naomi' => [850000, 650],
            'benjamin' => [5100000, 3200],
            'bola' => [1200000, 400],
            'waidi' => [2700000, 1450],
        ];

        foreach ($balances as $key => [$ngn, $usd]) {
            $user = $this->users[$key];
            $this->wallets[$key]['NGN'] = Wallet::updateOrCreate(
                ['user_id' => $user->id, 'currency' => 'NGN'],
                [
                    'account_number' => 'XAVNGN'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                    'balance' => $ngn,
                    'ngn_cleared' => $ngn,
                    'ngn_uncleared' => 0,
                    'usd_cleared' => 0,
                    'usd_uncleared' => 0,
                    'locked' => 0,
                    'status' => 'active',
                ]
            );

            $this->wallets[$key]['USD'] = Wallet::updateOrCreate(
                ['user_id' => $user->id, 'currency' => 'USD'],
                [
                    'account_number' => 'XAVUSD'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                    'balance' => $usd,
                    'ngn_cleared' => 0,
                    'ngn_uncleared' => 0,
                    'usd_cleared' => $usd,
                    'usd_uncleared' => 0,
                    'locked' => 0,
                    'status' => 'active',
                ]
            );
        }
    }

    private function seedTransactionTypesAndCharges(): void
    {
        $types = [
            ['name' => 'deposit', 'category' => 'funding'],
            ['name' => 'withdrawal', 'category' => 'funding'],
            ['name' => 'buy_stock', 'category' => 'trading'],
            ['name' => 'sell_stock', 'category' => 'trading'],
            ['name' => 'buy_crypto', 'category' => 'trading'],
            ['name' => 'sell_crypto', 'category' => 'trading'],
            ['name' => 'fx_conversion', 'category' => 'funding'],
        ];

        foreach ($types as $type) {
            DB::table('transaction_types')->updateOrInsert(
                ['name' => $type['name']],
                array_merge($type, ['updated_at' => now(), 'created_at' => now()])
            );
        }

        foreach ([
            ['deposit', 'percentage', 1.0],
            ['withdrawal', 'flat', 100.0],
            ['buy_stock', 'percentage', 0.5],
            ['sell_stock', 'percentage', 0.5],
            ['buy_crypto', 'percentage', 0.5],
            ['sell_crypto', 'percentage', 0.5],
            ['fx_conversion', 'percentage', 0.05],
        ] as [$type, $chargeType, $value]) {
            DB::table('transaction_charges')->updateOrInsert(
                ['transaction_type' => $type],
                ['charge_type' => $chargeType, 'value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function seedFundingAndTransactions(): void
    {
        $rows = [
            ['key' => 'bade', 'type' => 'deposit', 'asset' => 'NGN', 'amount' => 1500000, 'status' => 'completed'],
            ['key' => 'bade', 'type' => 'withdrawal', 'asset' => 'NGN', 'amount' => 250000, 'status' => 'completed'],
            ['key' => 'olutoyin', 'type' => 'deposit', 'asset' => 'NGN', 'amount' => 750000, 'status' => 'completed'],
            ['key' => 'naomi', 'type' => 'deposit', 'asset' => 'NGN', 'amount' => 300000, 'status' => 'pending'],
            ['key' => 'benjamin', 'type' => 'trade', 'asset' => 'AAPL', 'amount' => 1250, 'status' => 'completed'],
            ['key' => 'bola', 'type' => 'fee', 'asset' => 'NGN', 'amount' => 2500, 'status' => 'completed'],
            ['key' => 'waidi', 'type' => 'transfer', 'asset' => 'NGN', 'amount' => 100000, 'status' => 'completed'],
        ];

        foreach ($rows as $i => $row) {
            $txn = Transaction::updateOrCreate(
                ['reference' => 'DEMO-TXN-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $this->users[$row['key']]->id,
                    'type' => $row['type'],
                    'asset' => $row['asset'],
                    'amount' => $row['amount'],
                    'status' => $row['status'],
                    'meta' => ['demo' => true, 'source' => $row['type'] === 'deposit' ? 'paystack' : 'xavier'],
                ]
            );

            if ($row['status'] === 'completed') {
                DB::table('platform_earnings')->updateOrInsert(
                    ['transaction_id' => $txn->id, 'source' => 'demo_transaction_fee'],
                    [
                        'amount' => round($row['amount'] * 0.0025, 2),
                        'currency' => 'NGN',
                        'amount_ngn' => round($row['amount'] * 0.0025, 2),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $newRows = [
            ['key' => 'bade', 'type' => 'deposit', 'amount' => 1500000, 'currency' => 'NGN', 'status' => 'completed'],
            ['key' => 'bade', 'type' => 'buy_stock', 'amount' => 450000, 'currency' => 'NGN', 'status' => 'completed'],
            ['key' => 'olutoyin', 'type' => 'sell_stock', 'amount' => 220000, 'currency' => 'NGN', 'status' => 'completed'],
            ['key' => 'naomi', 'type' => 'withdrawal', 'amount' => 100000, 'currency' => 'NGN', 'status' => 'pending'],
            ['key' => 'benjamin', 'type' => 'buy_crypto', 'amount' => 850, 'currency' => 'USD', 'status' => 'completed'],
            ['key' => 'waidi', 'type' => 'sell_crypto', 'amount' => 450, 'currency' => 'USD', 'status' => 'completed'],
            ['key' => 'olutoyin', 'type' => 'fx_conversion', 'amount' => 1000, 'currency' => 'USD', 'status' => 'completed'],
        ];

        foreach ($newRows as $i => $row) {
            $charge = match ($row['type']) {
                'deposit' => round($row['amount'] * 0.01, 2),
                'withdrawal' => 100,
                'buy_stock', 'sell_stock', 'buy_crypto', 'sell_crypto' => round($row['amount'] * 0.005, 2),
                'fx_conversion' => round($row['amount'] * 0.0005, 2),
                default => 0,
            };

            $txn = NewTransaction::updateOrCreate(
                ['tx_hash' => 'DEMO-NEWTXN-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $this->users[$row['key']]->id,
                    'type' => $row['type'],
                    'amount' => $row['amount'],
                    'currency' => $row['currency'],
                    'charge' => $charge,
                    'net_amount' => max(0, $row['amount'] - $charge),
                    'status' => $row['status'],
                    'confirmations' => $row['status'] === 'completed' ? 3 : 0,
                    'meta' => ['demo' => true, 'provider' => $row['type'] === 'deposit' ? 'paystack' : 'xavier'],
                ]
            );
            $this->newTransactions[$i] = $txn;

            if ($row['status'] === 'completed') {
                RevenueRecord::updateOrCreate(
                    ['source' => 'Demo '.$row['type'], 'record_date' => now()->toDateString()],
                    [
                        'transaction_id' => null,
                        'currency' => $row['currency'],
                        'amount' => $charge,
                        'fee_percentage' => $row['amount'] > 0 ? ($charge / $row['amount']) * 100 : 0,
                        'description' => 'Demo revenue generated by '.$row['type'],
                        'record_date' => now()->toDateString(),
                    ]
                );
            }
        }
    }

    private function seedSecuritiesAndMarkets(): void
    {
        $symbols = [
            ['symbol' => 'MTNN', 'name' => 'MTN Nigeria Communications Plc', 'type' => 'Common Stock', 'exchange' => 'NGX', 'provider' => 'csl', 'price' => 520.00, 'isin' => 'NGMTNN000002'],
            ['symbol' => 'GTCO', 'name' => 'Guaranty Trust Holding Company Plc', 'type' => 'Common Stock', 'exchange' => 'NGX', 'provider' => 'csl', 'price' => 82.50, 'isin' => 'NGGTCO000001'],
            ['symbol' => 'ZENITHBANK', 'name' => 'Zenith Bank Plc', 'type' => 'Common Stock', 'exchange' => 'NGX', 'provider' => 'csl', 'price' => 58.40, 'isin' => 'NGZENITH0001'],
            ['symbol' => 'DANGCEM', 'name' => 'Dangote Cement Plc', 'type' => 'Common Stock', 'exchange' => 'NGX', 'provider' => 'csl', 'price' => 610.00, 'isin' => 'NGDANGCEM001'],
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'type' => 'Common Stock', 'exchange' => 'NASDAQ', 'provider' => 'alpaca', 'price' => 245.20, 'isin' => 'US0378331005'],
            ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation', 'type' => 'Common Stock', 'exchange' => 'NASDAQ', 'provider' => 'alpaca', 'price' => 510.10, 'isin' => 'US5949181045'],
            ['symbol' => 'TSLA', 'name' => 'Tesla Inc.', 'type' => 'Common Stock', 'exchange' => 'NASDAQ', 'provider' => 'alpaca', 'price' => 355.40, 'isin' => 'US88160R1014'],
            ['symbol' => 'NVDA', 'name' => 'NVIDIA Corporation', 'type' => 'Common Stock', 'exchange' => 'NASDAQ', 'provider' => 'alpaca', 'price' => 184.70, 'isin' => 'US67066G1040'],
            ['symbol' => 'BTC', 'name' => 'Bitcoin', 'type' => 'Crypto', 'exchange' => 'CRYPTO', 'provider' => null, 'price' => 114000.00, 'isin' => null],
            ['symbol' => 'ETH', 'name' => 'Ethereum', 'type' => 'Crypto', 'exchange' => 'CRYPTO', 'provider' => null, 'price' => 4200.00, 'isin' => null],
        ];

        foreach ($symbols as $item) {
            Symbol::updateOrCreate(
                ['symbol' => $item['symbol']],
                [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'exchange' => $item['exchange'],
                    'provider' => $item['provider'],
                    'provider_symbol_id' => $item['provider'] ? strtoupper($item['provider']).'-'.$item['symbol'] : null,
                    'market_id' => $item['exchange'],
                    'product_id' => 'DEMO-PRODUCT-'.$item['symbol'],
                    'isin' => $item['isin'],
                    'provider_symbol_type' => $item['type'],
                    'provider_metadata' => ['demo' => true],
                    'last_price' => $item['price'],
                    'change' => 1.25,
                    'volume' => 125000,
                ]
            );

            foreach (['bade', 'olutoyin', 'naomi'] as $key) {
                Watchlist::updateOrCreate(
                    ['user_id' => $this->users[$key]->id, 'symbol' => $item['symbol']],
                    [
                        'name' => $item['name'],
                        'market' => $item['exchange'],
                        'currency' => in_array($item['exchange'], ['NGX'], true) ? 'NGN' : 'USD',
                        'added_price' => $item['price'],
                    ]
                );
            }
        }
    }

    private function seedOrdersTradesSettlements(): void
    {
        $orders = [
            ['key' => 'bade', 'symbol' => 'MTNN', 'market' => 'NGX', 'currency' => 'NGN', 'side' => 'buy', 'qty' => 500, 'price' => 510, 'status' => 'filled', 'provider' => 'csl', 'provider_order_id' => 'CSL-DEMO-0001'],
            ['key' => 'olutoyin', 'symbol' => 'GTCO', 'market' => 'NGX', 'currency' => 'NGN', 'side' => 'sell', 'qty' => 800, 'price' => 81, 'status' => 'filled', 'provider' => 'csl', 'provider_order_id' => 'CSL-DEMO-0002'],
            ['key' => 'benjamin', 'symbol' => 'AAPL', 'market' => 'GLOBAL', 'currency' => 'USD', 'side' => 'buy', 'qty' => 5, 'price' => 240, 'status' => 'filled', 'provider' => 'alpaca', 'provider_order_id' => 'ALP-DEMO-0001'],
            ['key' => 'waidi', 'symbol' => 'MSFT', 'market' => 'GLOBAL', 'currency' => 'USD', 'side' => 'buy', 'qty' => 3, 'price' => 500, 'status' => 'partially_filled', 'provider' => 'alpaca', 'provider_order_id' => 'ALP-DEMO-0002'],
            ['key' => 'naomi', 'symbol' => 'ZENITHBANK', 'market' => 'NGX', 'currency' => 'NGN', 'side' => 'buy', 'qty' => 1000, 'price' => 57, 'status' => 'open', 'provider' => 'csl', 'provider_order_id' => 'CSL-DEMO-0003'],
            ['key' => 'bola', 'symbol' => 'TSLA', 'market' => 'GLOBAL', 'currency' => 'USD', 'side' => 'sell', 'qty' => 2, 'price' => 350, 'status' => 'canceled', 'provider' => 'alpaca', 'provider_order_id' => 'ALP-DEMO-0003'],
        ];

        foreach ($orders as $index => $data) {
            $filled = match ($data['status']) {
                'filled' => $data['qty'],
                'partially_filled' => $data['qty'] / 2,
                default => 0,
            };

            $order = Order::updateOrCreate(
                ['provider' => $data['provider'], 'provider_order_id' => $data['provider_order_id']],
                [
                    'user_id' => $this->users[$data['key']]->id,
                    'symbol' => $data['symbol'],
                    'side' => $data['side'],
                    'type' => 'limit',
                    'price' => $data['price'],
                    'quantity' => $data['qty'],
                    'filled_quantity' => $filled,
                    'status' => $data['status'],
                    'position_type' => 'order',
                    'source' => 'web',
                    'market' => $data['market'],
                    'currency' => $data['currency'],
                    'company' => Symbol::where('symbol', $data['symbol'])->value('name'),
                    'units' => $data['qty'],
                    'amount' => $data['qty'] * $data['price'],
                    'market_price' => $data['price'],
                    'limit_price' => $data['price'],
                    'provider_market_account_id' => $data['provider'].'-DEMO-MARKET-ACCOUNT',
                    'time_in_force' => 'day',
                    'provider_request' => ['demo' => true, 'symbol' => $data['symbol']],
                    'provider_response' => ['demo' => true, 'status' => strtoupper($data['status'])],
                    'provider_submitted_at' => now()->subMinutes(30 + $index),
                    'provider_client_reference' => 'XAV-DEMO-ORDER-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'last_reconciled_at' => now()->subMinutes(5),
                    'reconciliation_status' => $data['status'] === 'canceled' ? 'cancelled' : 'matched',
                    'provider_cancellation_status' => $data['status'] === 'canceled' ? 'confirmed' : null,
                ]
            );
            $this->orders[$index] = $order;

            if ($filled > 0) {
                $trade = Trade::updateOrCreate(
                    ['provider' => $data['provider'], 'reference' => 'DEMO-TRADE-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                    [
                        'order_id' => $order->id,
                        'counterparty_order_id' => null,
                        'price' => $data['price'],
                        'quantity' => $filled,
                        'fee' => round($filled * $data['price'] * 0.005, 2),
                        'settlement_status' => $data['provider'] === 'csl' && $data['status'] === 'filled' ? 'settled' : 'pending',
                        'settlement_date' => $data['provider'] === 'csl' && $data['status'] === 'filled' ? now()->subDay()->toDateString() : null,
                        'user_id' => $this->users[$data['key']]->id,
                        'pair' => $data['symbol'].'/'.$data['currency'],
                        'type' => $data['side'],
                        'amount' => $filled * $data['price'],
                        'entry_price' => $data['price'],
                        'exit_price' => $data['side'] === 'sell' ? $data['price'] : null,
                        'profit_loss' => $data['side'] === 'sell' ? 3500 : null,
                        'status' => 'completed',
                        'is_settled' => $data['provider'] === 'csl' && $data['status'] === 'filled',
                        'provider_trade_id' => strtoupper($data['provider']).'-TRADE-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'provider_order_id' => $data['provider_order_id'],
                        'provider_response' => ['demo' => true, 'executed_quantity' => $filled],
                        'provider_executed_at' => now()->subHours(2 + $index),
                    ]
                );
                $this->trades[$index] = $trade;

                Fee::updateOrCreate(
                    ['user_id' => $this->users[$data['key']]->id, 'trade_id' => $trade->id],
                    ['amount' => round($filled * $data['price'] * 0.005, 2), 'type' => 'trade_fee']
                );

                if ($data['status'] === 'filled') {
                    DB::table('settlements')->updateOrInsert(
                        ['trade_id' => $trade->id],
                        [
                            'status' => $trade->is_settled ? 'settled' : 'pending',
                            'settlement_date' => $trade->settlement_date ?? now()->addDay()->toDateString(),
                            'reference' => 'SETTLE-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );

                    DB::table('contract_notes')->updateOrInsert(
                        ['trade_id' => $trade->id],
                        [
                            'file_path' => 'contract-notes/demo/DEMO-TRADE-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).'.pdf',
                            'generated_at' => now()->subHours(1),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function seedPortfoliosAndPositions(): void
    {
        $holdings = [
            ['bade', 'MTNN', 'MTN Nigeria Communications Plc', 'local', 500, 510, 520, 'NGN'],
            ['bade', 'DANGCEM', 'Dangote Cement Plc', 'local', 100, 590, 610, 'NGN'],
            ['olutoyin', 'GTCO', 'Guaranty Trust Holding Company Plc', 'local', 800, 75, 82.5, 'NGN'],
            ['benjamin', 'AAPL', 'Apple Inc.', 'foreign', 5, 240, 245.2, 'USD'],
            ['benjamin', 'BTC', 'Bitcoin', 'crypto', 0.025, 108000, 114000, 'USD'],
            ['waidi', 'MSFT', 'Microsoft Corporation', 'foreign', 1.5, 490, 510.1, 'USD'],
            ['waidi', 'ETH', 'Ethereum', 'crypto', 0.35, 3900, 4200, 'USD'],
            ['naomi', 'ZENITHBANK', 'Zenith Bank Plc', 'local', 250, 55, 58.4, 'NGN'],
        ];

        foreach ($holdings as [$key, $symbol, $name, $category, $qty, $avg, $market, $currency]) {
            Portfolio::updateOrCreate(
                ['user_id' => $this->users[$key]->id, 'symbol' => $symbol],
                [
                    'name' => $name,
                    'category' => $category,
                    'quantity' => $qty,
                    'cleared_quantity' => (int) floor($qty),
                    'uncleared_quantity' => 0,
                    'avg_price' => $avg,
                    'market_price' => $market,
                    'currency' => $currency,
                ]
            );

            DB::table('positions')->updateOrInsert(
                ['user_id' => $this->users[$key]->id, 'symbol' => $symbol],
                [
                    'qty' => $qty,
                    'avg_price' => $avg,
                    'position_type' => 'long',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        foreach ($this->users as $key => $user) {
            if ($key === 'admin') {
                continue;
            }
            $marketValue = (float) Portfolio::where('user_id', $user->id)->get()->sum(fn ($p) => $p->quantity * $p->market_price);
            $cost = (float) Portfolio::where('user_id', $user->id)->get()->sum(fn ($p) => $p->quantity * $p->avg_price);
            DB::table('portfolio_snapshots')->updateOrInsert(
                ['user_id' => $user->id, 'snapshot_date' => now()->toDateString()],
                [
                    'market_value' => round($marketValue, 2),
                    'cost_basis' => round($cost, 2),
                    'cash_balance' => (float) ($this->wallets[$key]['NGN']->balance ?? 0),
                    'gain' => round($marketValue - $cost, 2),
                    'roi' => $cost > 0 ? round((($marketValue - $cost) / $cost) * 100, 4) : 0,
                    'currency' => 'NGN',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedFixedIncome(): void
    {
        $products = [
            ['code' => 'FGNSB-DEMO-2027', 'name' => 'FGN Savings Bond 2027', 'type' => 'bond', 'rate' => 14.5, 'tenor' => 365, 'minimum' => 10000, 'maximum' => 5000000, 'early' => false],
            ['code' => 'CP-MTN-DEMO', 'name' => 'MTN Commercial Paper Demo', 'type' => 'commercial_paper', 'rate' => 18.25, 'tenor' => 180, 'minimum' => 100000, 'maximum' => 10000000, 'early' => true],
            ['code' => 'FGN-TB-DEMO-91', 'name' => 'FGN Treasury Bill Demo 91-Day', 'type' => 'treasury_bill', 'rate' => 16.0, 'tenor' => 91, 'minimum' => 5000, 'maximum' => 5000000, 'early' => false],
        ];

        $productModels = [];
        foreach ($products as $p) {
            $productModels[$p['code']] = FixedIncomeProduct::updateOrCreate(
                ['code' => $p['code']],
                [
                    'name' => $p['name'],
                    'type' => $p['type'],
                    'description' => 'Demo fixed-income product for UAT and reporting.',
                    'currency' => 'NGN',
                    'issuer' => 'Federal Government / Demo Provider',
                    'status' => 'active',
                    'minimum_amount' => $p['minimum'],
                    'maximum_amount' => $p['maximum'],
                    'maximum_open_ended' => false,
                    'start_date' => now()->subDays(30)->toDateString(),
                    'end_date' => now()->addDays($p['tenor'])->toDateString(),
                    'open_ended' => false,
                    'interest_rate' => $p['rate'],
                    'rate_type' => 'fixed',
                    'interest_frequency' => 'at_maturity',
                    'tenor_days' => $p['tenor'],
                    'early_withdrawal_allowed' => $p['early'],
                    'early_withdrawal_penalty' => $p['early'] ? 2.5 : 0,
                    'subscription_fee' => 0.25,
                    'subscription_fee_type' => 'percentage',
                    'maximum_capacity' => 100000000,
                    'maximum_user_capacity' => 10000000,
                    'execution_mode' => 'manual',
                    'provider' => 'demo_fixed_income_provider',
                    'allow_reinvestment' => true,
                    'calculation_method' => 'simple_interest',
                    'day_count_basis' => 'actual_365',
                    'capitalise_interest' => false,
                    'maturity_payout' => 'wallet',
                    'metadata' => ['demo' => true],
                ]
            );
        }

        $investments = [
            ['key' => 'bade', 'product' => 'FGNSB-DEMO-2027', 'principal' => 1000000, 'status' => 'active', 'days_ago' => 45],
            ['key' => 'olutoyin', 'product' => 'CP-MTN-DEMO', 'principal' => 500000, 'status' => 'matured', 'days_ago' => 220],
            ['key' => 'benjamin', 'product' => 'FGN-TB-DEMO-91', 'principal' => 250000, 'status' => 'redeemed', 'days_ago' => 130],
            ['key' => 'waidi', 'product' => 'CP-MTN-DEMO', 'principal' => 750000, 'status' => 'pending', 'days_ago' => 1],
        ];

        foreach ($investments as $i => $item) {
            $product = $productModels[$item['product']];
            $investmentDate = now()->subDays($item['days_ago']);
            $interest = round($item['principal'] * ($product->interest_rate / 100) * ($product->tenor_days / 365), 2);
            $maturity = round($item['principal'] + $interest, 2);

            $investment = FixedIncomeInvestment::updateOrCreate(
                ['reference' => 'DEMO-FI-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $this->users[$item['key']]->id,
                    'fixed_income_product_id' => $product->id,
                    'idempotency_key' => 'DEMO-FI-IDEMP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'principal_amount' => $item['principal'],
                    'currency' => 'NGN',
                    'interest_rate' => $product->interest_rate,
                    'rate_type' => 'fixed',
                    'expected_interest' => $interest,
                    'expected_maturity_amount' => $maturity,
                    'actual_interest' => in_array($item['status'], ['matured', 'redeemed'], true) ? $interest : null,
                    'actual_maturity_amount' => in_array($item['status'], ['matured', 'redeemed'], true) ? $maturity : null,
                    'status' => $item['status'],
                    'investment_date' => $investmentDate,
                    'execution_date' => $item['status'] === 'pending' ? null : $investmentDate->copy()->addDay(),
                    'maturity_date' => $investmentDate->copy()->addDays($product->tenor_days),
                    'redeemed_at' => $item['status'] === 'redeemed' ? now()->subDays(20) : null,
                    'funding_method' => 'wallet',
                    'execution_mode' => 'manual',
                    'provider' => 'demo_fixed_income_provider',
                    'provider_reference' => 'DEMO-FIP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'reinvestment_enabled' => $item['key'] === 'bade',
                    'reserved_amount' => $item['status'] === 'pending' ? $item['principal'] : 0,
                    'funded_at' => $item['status'] === 'pending' ? null : $investmentDate,
                    'last_status_at' => now()->subHours($i + 1),
                    'metadata' => ['demo' => true],
                ]
            );

            foreach (array_values(array_filter([
                ['type' => 'subscription', 'amount' => $item['principal'], 'status' => 'completed'],
                $item['status'] !== 'pending' ? ['type' => 'interest_accrual', 'amount' => $interest, 'status' => 'completed'] : null,
                $item['status'] === 'redeemed' ? ['type' => 'redemption', 'amount' => $maturity, 'status' => 'completed'] : null,
            ])) as $j => $tx) {
                FixedIncomeTransaction::updateOrCreate(
                    ['reference' => 'DEMO-FITX-'.$investment->id.'-'.$j],
                    [
                        'fixed_income_investment_id' => $investment->id,
                        'user_id' => $investment->user_id,
                        'type' => $tx['type'],
                        'amount' => $tx['amount'],
                        'currency' => 'NGN',
                        'status' => $tx['status'],
                        'transaction_id' => null,
                        'ledger_id' => null,
                        'metadata' => ['demo' => true],
                    ]
                );
            }
        }
    }

    private function seedFx(): void
    {
        $pairs = [
            ['base_currency' => 'USD', 'quote_currency' => 'NGN', 'buy_rate' => 1520, 'sell_rate' => 1490],
            ['base_currency' => 'GBP', 'quote_currency' => 'NGN', 'buy_rate' => 2040, 'sell_rate' => 2000],
            ['base_currency' => 'EUR', 'quote_currency' => 'NGN', 'buy_rate' => 1760, 'sell_rate' => 1725],
        ];
        foreach ($pairs as $pair) {
            DB::table('fx_pairs')->updateOrInsert(
                ['base_currency' => $pair['base_currency'], 'quote_currency' => $pair['quote_currency']],
                array_merge($pair, ['active' => true, 'updated_at' => now(), 'created_at' => now()])
            );
            DB::table('fx_rates')->updateOrInsert(
                ['from_currency' => $pair['base_currency'], 'to_currency' => $pair['quote_currency']],
                [
                    'base_rate' => ($pair['buy_rate'] + $pair['sell_rate']) / 2,
                    'markup_percent' => 1.0,
                    'effective_rate' => $pair['sell_rate'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $conversions = [
            ['key' => 'olutoyin', 'ref' => 'DEMO-FX-0001', 'from' => 'USD', 'to' => 'NGN', 'amount' => 1000, 'rate' => 1490, 'status' => 'completed'],
            ['key' => 'bade', 'ref' => 'DEMO-FX-0002', 'from' => 'NGN', 'to' => 'USD', 'amount' => 745000, 'rate' => 1490, 'status' => 'completed'],
            ['key' => 'waidi', 'ref' => 'DEMO-FX-0003', 'from' => 'USD', 'to' => 'NGN', 'amount' => 500, 'rate' => 1490, 'status' => 'pending'],
        ];

        foreach ($conversions as $item) {
            $converted = $item['from'] === 'USD'
                ? $item['amount'] * $item['rate']
                : $item['amount'] / $item['rate'];

            FxConversion::updateOrCreate(
                ['reference' => $item['ref']],
                [
                    'user_id' => $this->users[$item['key']]->id,
                    'provider' => 'fincra',
                    'from_currency' => $item['from'],
                    'to_currency' => $item['to'],
                    'amount' => $item['amount'],
                    'rate' => $item['rate'],
                    'converted_amount' => round($converted, 6),
                    'status' => $item['status'],
                    'response' => ['demo' => true, 'provider_reference' => 'FINCRA-DEMO-'.$item['ref']],
                ]
            );
        }
    }

    private function seedBankingAndWithdrawals(): void
    {
        foreach (['bade', 'olutoyin', 'benjamin', 'waidi'] as $key) {
            LinkedAccount::updateOrCreate(
                ['user_id' => $this->users[$key]->id, 'type' => 'bank', 'provider' => 'paystack'],
                [
                    'currency' => 'NGN',
                    'account_name' => $this->users[$key]->name,
                    'account_number' => '10'.str_pad((string) $this->users[$key]->id, 8, '0', STR_PAD_LEFT),
                    'is_verified' => true,
                ]
            );
        }

        $withdrawals = [
            ['key' => 'bade', 'amount' => 250000, 'status' => 'completed'],
            ['key' => 'naomi', 'amount' => 100000, 'status' => 'pending'],
            ['key' => 'bola', 'amount' => 75000, 'status' => 'rejected'],
            ['key' => 'waidi', 'amount' => 180000, 'status' => 'approved'],
        ];

        foreach ($withdrawals as $i => $item) {
            WithdrawalRequest::updateOrCreate(
                ['user_id' => $this->users[$item['key']]->id, 'account_number' => '10'.str_pad((string) $this->users[$item['key']]->id, 8, '0', STR_PAD_LEFT), 'amount' => $item['amount']],
                [
                    'currency' => 'NGN',
                    'bank_code' => '058',
                    'account_name' => $this->users[$item['key']]->name,
                    'status' => $item['status'],
                    'rejection_reason' => $item['status'] === 'rejected' ? 'Demo rejected request for workflow testing.' : null,
                    'reviewed_by' => in_array($item['status'], ['approved', 'rejected', 'completed'], true) ? $this->users['admin']->id : null,
                    'reviewed_at' => in_array($item['status'], ['approved', 'rejected', 'completed'], true) ? now()->subHours($i + 1) : null,
                    'completed_at' => $item['status'] === 'completed' ? now()->subHours(2) : null,
                    'metadata' => ['provider' => 'paystack', 'demo' => true],
                ]
            );
        }

        foreach (['bade', 'olutoyin', 'naomi', 'benjamin', 'bola', 'waidi'] as $i => $key) {
            WalletTransaction::updateOrCreate(
                ['reference' => 'DEMO-WTX-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $this->users[$key]->id,
                    'wallet_currency' => 'NGN',
                    'type' => $i % 2 === 0 ? 'credit' : 'debit',
                    'amount' => 50000 + ($i * 25000),
                    'note' => 'Demo wallet transaction',
                ]
            );

            Ledger::updateOrCreate(
                ['reference' => 'DEMO-LEDGER-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $this->users[$key]->id,
                    'currency' => 'NGN',
                    'amount' => $i % 2 === 0 ? 50000 : -50000,
                    'type' => $i % 2 === 0 ? 'deposit' : 'withdrawal',
                    'status' => 'completed',
                    'meta' => ['demo' => true],
                    'is_platform' => false,
                ]
            );
        }
    }

    private function seedSubscriptionsAndBilling(): void
    {
        $plans = [
            ['name' => 'Demo Basic', 'price' => 5000, 'duration_days' => 30, 'tier' => 'regular'],
            ['name' => 'Demo Premium', 'price' => 15000, 'duration_days' => 30, 'tier' => 'premium'],
            ['name' => 'Demo Pro', 'price' => 30000, 'duration_days' => 30, 'tier' => 'pro'],
        ];

        $planModels = [];
        foreach ($plans as $plan) {
            $planModels[$plan['tier']] = SubscriptionPlan::updateOrCreate(
                ['name' => $plan['name']],
                [
                    'price' => $plan['price'],
                    'duration_days' => $plan['duration_days'],
                    'features' => json_encode(['demo trading', 'reports', 'portfolio analytics']),
                    'paystack_plan_code' => 'PLN_DEMO_'.strtoupper($plan['tier']),
                    'tier' => $plan['tier'],
                ]
            );
        }

        $map = [
            'bade' => ['premium', 'active'],
            'olutoyin' => ['regular', 'active'],
            'naomi' => ['regular', 'trial'],
            'benjamin' => ['pro', 'active'],
            'bola' => ['regular', 'expired'],
            'waidi' => ['premium', 'active'],
        ];

        foreach ($map as $key => [$tier, $status]) {
            $plan = $planModels[$tier];
            UserSubscription::updateOrCreate(
                ['user_id' => $this->users[$key]->id, 'subscription_plan_id' => $plan->id],
                [
                    'expires_at' => $status === 'expired' ? now()->subDays(5) : now()->addDays(25),
                    'paystack_subscription_code' => 'SUB_DEMO_'.$this->users[$key]->id,
                    'status' => $status,
                ]
            );

            BillingRecord::updateOrCreate(
                ['reference' => 'BILL-DEMO-'.$this->users[$key]->id],
                [
                    'user_id' => $this->users[$key]->id,
                    'amount' => $plan->price,
                    'type' => 'subscription_fee',
                    'status' => $status === 'expired' ? 'failed' : 'paid',
                ]
            );
        }
    }

    private function seedExpenses(): void
    {
        $departments = [
            ['Technology', 'TECH'], ['Marketing', 'MKT'], ['Finance', 'FIN'], ['Operations', 'OPS'], ['Human Resources', 'HR'], ['Compliance', 'COMP'],
        ];
        $departmentModels = [];
        foreach ($departments as [$name, $code]) {
            $departmentModels[$name] = Department::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $name.' demo department', 'is_active' => true]
            );
        }

        $categories = [
            ['Salaries', 'SAL', 'Employee salary and payroll expenses'],
            ['Administrative Expenses', 'ADMIN', 'General administrative expenses'],
            ['Legal Expenses', 'LEGAL', 'Legal and regulatory expenses'],
            ['Office Rent', 'RENT', 'Office space rental expenses'],
            ['Utilities', 'UTIL', 'Electricity and utilities'],
            ['Cloud Services', 'CLOUD', 'Cloud infrastructure'],
            ['Marketing', 'MKT', 'Marketing expenses'],
            ['Professional Fees', 'PROF', 'Professional services'],
        ];
        $categoryModels = [];
        foreach ($categories as [$name, $code, $description]) {
            $categoryModels[$name] = ExpenseCategory::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $description, 'is_active' => true]
            );
        }

        $vendors = [
            ['name' => 'Xavier Payroll', 'contact_person' => 'HR Team', 'email' => 'hr@demo.xavier.local'],
            ['name' => 'Demo Law Partners', 'contact_person' => 'Legal Team', 'email' => 'legal@demo.xavier.local'],
            ['name' => 'Demo Office Landlord', 'contact_person' => 'Property Manager', 'email' => 'landlord@demo.xavier.local'],
            ['name' => 'AWS', 'contact_person' => 'Account Team', 'email' => 'aws@amazon.com'],
            ['name' => 'MTN', 'contact_person' => 'Corporate Sales', 'email' => 'corporate@mtn.com'],
        ];
        $vendorModels = [];
        foreach ($vendors as $vendor) {
            $vendorModels[$vendor['name']] = Vendor::updateOrCreate(
                ['name' => $vendor['name']],
                array_merge($vendor, ['phone' => '+2340000000000', 'is_active' => true])
            );
        }

        $expenses = [
            ['SAL', 'Xavier Payroll', 'Human Resources', 4800000, 'paid', 'Monthly salaries - engineering and operations'],
            ['SAL', 'Xavier Payroll', 'Human Resources', 2650000, 'approved', 'Monthly salaries - support and administration'],
            ['ADMIN', null, 'Operations', 175000, 'paid', 'Administrative supplies and office administration'],
            ['ADMIN', 'Demo Office Landlord', 'Finance', 1200000, 'paid', 'Office administration and facility management'],
            ['LEGAL', 'Demo Law Partners', 'Finance', 450000, 'paid', 'Corporate legal advisory and regulatory review'],
            ['LEGAL', 'Demo Law Partners', 'Compliance', 300000, 'approved', 'KYC and regulatory documentation review'],
            ['CLOUD', 'AWS', 'Technology', 350000, 'paid', 'Production cloud infrastructure'],
            ['UTIL', 'MTN', 'Operations', 95000, 'paid', 'Corporate connectivity and utilities'],
            ['MKT', null, 'Marketing', 500000, 'draft', 'Demo campaign and advertising budget'],
            ['PROF', 'Demo Law Partners', 'Finance', 225000, 'cancelled', 'Cancelled professional advisory engagement'],
        ];

        foreach ($expenses as $i => [$code, $vendorName, $departmentName, $amount, $status, $description]) {
            $expense = Expense::updateOrCreate(
                ['invoice_number' => 'DEMO-INV-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'expense_category_id' => $categoryModels[$this->categoryNameFromCode($code)]->id,
                    'vendor_id' => $vendorName ? $vendorModels[$vendorName]->id : null,
                    'department_id' => $departmentModels[$departmentName]->id ?? $departmentModels['Finance']->id,
                    'requested_by' => $this->users['admin']->id,
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'expense_date' => now()->subDays($i + 1)->toDateString(),
                    'payment_method' => $status === 'paid' ? 'bank_transfer' : 'other',
                    'reference' => 'DEMO-EXP-REF-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'description' => $description,
                    'status' => $status,
                ]
            );
            Expense::assignExpenseNo($expense);
        }
    }

    private function categoryNameFromCode(string $code): string
    {
        return match ($code) {
            'SAL' => 'Salaries',
            'ADMIN' => 'Administrative Expenses',
            'LEGAL' => 'Legal Expenses',
            'RENT' => 'Office Rent',
            'UTIL' => 'Utilities',
            'CLOUD' => 'Cloud Services',
            'MKT' => 'Marketing',
            'PROF' => 'Professional Fees',
            default => 'Administrative Expenses',
        };
    }

    private function seedReportsAndAnalytics(): void
    {
        $reportNames = [
            ['name' => 'Portfolio Statement', 'type' => 'statement'],
            ['name' => 'Trading Activity Report', 'type' => 'trading'],
            ['name' => 'Transaction History', 'type' => 'transactions'],
            ['name' => 'Revenue Report', 'type' => 'revenue'],
            ['name' => 'Expense Report', 'type' => 'expenses'],
            ['name' => 'Fixed Income Report', 'type' => 'fixed_income'],
            ['name' => 'FX Conversion Report', 'type' => 'fx'],
            ['name' => 'CSL Reconciliation Report', 'type' => 'csl_reconciliation'],
        ];

        foreach ($this->users as $key => $user) {
            if ($key === 'admin') {
                continue;
            }
            foreach ($reportNames as $index => $report) {
                ReportHistory::updateOrCreate(
                    ['user_id' => $user->id, 'name' => $report['name'], 'period' => 'monthly'],
                    [
                        'type' => $report['type'],
                        'format' => ['pdf', 'excel', 'csv'][$index % 3],
                        'wallet' => 'all',
                        'start_date' => now()->startOfMonth()->toDateString(),
                        'end_date' => now()->toDateString(),
                        'status' => 'completed',
                    ]
                );
            }
        }

        foreach ($reportNames as $index => $report) {
            ReportTemplate::updateOrCreate(
                ['name' => 'Demo '.$report['name']],
                [
                    'category' => $report['type'],
                    'filters' => ['demo' => true, 'period' => 'monthly'],
                    'created_by' => $this->users['admin']->id,
                    'is_public' => true,
                ]
            );
        }

        foreach (['pdf', 'excel', 'csv'] as $index => $format) {
            ReportExport::updateOrCreate(
                ['file_name' => 'demo-'.$format.'-report.'.($format === 'excel' ? 'xlsx' : $format)],
                [
                    'user_id' => $this->users['admin']->id,
                    'report_name' => 'Demo Financial Report',
                    'export_type' => $format,
                    'disk' => 'local',
                    'path' => 'reports/demo/demo-'.$format.'-report.'.($format === 'excel' ? 'xlsx' : $format),
                    'status' => $index === 2 ? 'failed' : 'completed',
                    'generated_at' => $index === 2 ? null : now()->subMinutes(20),
                    'expires_at' => $index === 2 ? null : now()->addDays(7),
                    'downloaded_at' => $index === 0 ? now()->subMinutes(10) : null,
                ]
            );
        }

        $schedules = [
            ['name' => 'Daily Revenue Report', 'frequency' => 'daily', 'email' => ['admin@demo.xavier.local']],
            ['name' => 'Weekly Portfolio Report', 'frequency' => 'weekly', 'email' => ['bade.ayoade@demo.xavier.local']],
            ['name' => 'Monthly Expense Report', 'frequency' => 'monthly', 'email' => ['finance@demo.xavier.local']],
        ];
        foreach ($schedules as $schedule) {
            ReportSchedule::updateOrCreate(
                ['report_name' => $schedule['name']],
                [
                    'frequency' => $schedule['frequency'],
                    'filters' => ['demo' => true],
                    'email_to' => $schedule['email'],
                    'last_run' => now()->subDay(),
                    'next_run' => now()->addDay(),
                    'enabled' => true,
                ]
            );
        }

        $metrics = [
            'active_users' => User::where('status', 'active')->count(),
            'total_wallet_balance_ngn' => (float) Wallet::where('currency', 'NGN')->sum('balance'),
            'total_portfolio_value' => (float) Portfolio::get()->sum(fn ($p) => $p->quantity * $p->market_price),
            'trades_today' => Trade::whereDate('created_at', now()->toDateString())->count(),
            'open_orders' => Order::whereIn('status', ['open', 'partially_filled'])->count(),
            'fixed_income_principal' => (float) FixedIncomeInvestment::sum('principal_amount'),
            'expenses_this_month' => (float) Expense::whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount'),
        ];
        foreach ($metrics as $metric => $value) {
            DB::table('analytics_snapshots')->updateOrInsert(
                ['metric' => $metric, 'snapshot_date' => now()->toDateString()],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach (['portfolio_value', 'cash_balance', 'recent_orders', 'market_summary', 'expense_summary', 'revenue_summary'] as $position => $widget) {
            DB::table('dashboard_widgets')->updateOrInsert(
                ['user_id' => $this->users['admin']->id, 'widget' => $widget],
                ['position' => $position, 'settings' => json_encode(['demo' => true]), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function seedNotificationsAndAudit(): void
    {
        foreach (['bade', 'olutoyin', 'naomi', 'benjamin', 'waidi'] as $i => $key) {
            $user = $this->users[$key];
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'email' => true,
                    'sms' => $i % 2 === 0,
                    'push' => true,
                    'monthly_statements' => true,
                    'newsletters' => $i % 2 === 0,
                ]
            );

            DB::table('notifications')
                ->where('user_id', $user->id)
                ->where('type', 'demo.account_update')
                ->delete();

            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'demo.account_update',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'user_id' => $user->id,
                'title' => 'Demo account update',
                'message' => 'Your Xavier demo account has updated portfolio and transaction data.',
                'action' => 'view_portfolio',
                'action_url' => '/portfolio',
                'icon' => 'chart',
                'data' => json_encode(['demo' => true]),
                'metadata' => json_encode(['demo' => true]),
                'read_at' => $i % 2 === 0 ? now()->subHours(2) : null,
                'created_at' => now()->subHours($i + 1),
                'updated_at' => now(),
            ]);

            LoginHistory::updateOrCreate(
                ['user_id' => $user->id, 'ip_address' => '197.210.00.'.($i + 10), 'logged_in_at' => now()->subHours($i + 2)],
                [
                    'device' => 'Desktop',
                    'browser' => 'Chrome',
                    'platform' => 'Windows',
                    'location' => 'Lagos, Nigeria',
                    'successful' => true,
                ]
            );

            UserSession::updateOrCreate(
                ['session_id' => 'DEMO-SESSION-'.$user->id],
                [
                    'user_id' => $user->id,
                    'ip_address' => '197.210.00.'.($i + 10),
                    'device' => 'Desktop',
                    'browser' => 'Chrome',
                    'platform' => 'Windows',
                    'last_activity' => now()->subMinutes($i * 5),
                ]
            );

            RiskFlag::updateOrCreate(
                ['user_id' => $user->id, 'type' => $i % 2 === 0 ? 'velocity_check' : 'manual_review'],
                [
                    'severity' => $i === 2 ? 'high' : 'low',
                    'meta' => ['demo' => true, 'reviewed' => $i !== 2],
                ]
            );
        }

        if (count($this->newTransactions) > 0) {
            $auditIndex = 0;

            foreach ($this->users as $userKey => $user) {
                if ($userKey === 'admin') {
                    continue;
                }

                $transaction = $this->newTransactions[
                    $auditIndex % count($this->newTransactions)
                ];

                TransactionAudit::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'action' => 'demo_transaction_created',
                        'entity_type' => 'NewTransaction',
                        'entity_id' => $transaction->id,
                    ],
                    [
                        'old_values' => null,
                        'new_values' => [
                            'demo' => true,
                            'status' => 'completed',
                        ],
                        'ip_address' => '197.210.10.'.($auditIndex + 20),
                    ]
                );

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'demo_seed',
                    'entity' => 'User',
                    'entity_id' => $user->id,
                    'payload' => [
                        'demo' => true,
                        'message' => 'Showcase data generated',
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Xavier Demo Seeder',
                ]);

                DB::table('activity_logs')->insert([
                    'user_id' => $user->id,
                    'activity' => 'demo_seed_completed',
                    'properties' => json_encode([
                        'demo' => true,
                    ]),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Xavier Demo Seeder',
                    'details' => json_encode([
                        'source' => 'DemoShowcaseSeeder',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $auditIndex++;
            }
        }

    }

    private function seedProviderAndCslData(): void
    {
        $providerUsers = [
            ['key' => 'bade', 'status' => 'active'],
            ['key' => 'olutoyin', 'status' => 'active'],
            ['key' => 'benjamin', 'status' => 'active'],
            ['key' => 'waidi', 'status' => 'pending'],
            ['key' => 'naomi', 'status' => 'pending'],
        ];

        foreach ($providerUsers as $i => $item) {
            ProviderAccount::updateOrCreate(
                ['provider' => 'csl', 'market_account_id' => 'CSL-DEMO-ACCOUNT-'.($i + 1)],
                [
                    'user_id' => $this->users[$item['key']]->id,
                    'customer_id' => 'CSL-DEMO-CUSTOMER-'.($i + 1),
                    'market_id' => 'NGX',
                    'product_id' => 'NGX-EQUITIES',
                    'market_customer_id' => 'CSL-DEMO-MARKET-CUSTOMER-'.($i + 1),
                    'portfolio_id' => 'CSL-DEMO-PORTFOLIO-'.($i + 1),
                    'cash_funding_account_id' => 'CSL-DEMO-CASH-'.($i + 1),
                    'status' => $item['status'],
                    'metadata' => [
                        'demo' => true,
                        'account_name' => $this->users[$item['key']]->name,
                        'market' => 'NGX',
                    ],
                ]
            );
        }

        $logs = [
            ['operation' => 'authenticate', 'status' => 'success', 'severity' => 'info'],
            ['operation' => 'account_mapping', 'status' => 'success', 'severity' => 'info'],
            ['operation' => 'instrument_sync', 'status' => 'success', 'severity' => 'info'],
            ['operation' => 'market_status', 'status' => 'success', 'severity' => 'info'],
            ['operation' => 'order_submission', 'status' => 'success', 'severity' => 'info'],
            ['operation' => 'order_reconciliation', 'status' => 'partial', 'severity' => 'warning'],
            ['operation' => 'portfolio_sync', 'status' => 'failed', 'severity' => 'error'],
        ];
        foreach ($logs as $i => $log) {
            ProviderSyncLog::updateOrCreate(
                ['provider' => 'csl', 'operation' => $log['operation'], 'reference' => 'CSL-DEMO-LOG-'.($i + 1)],
                [
                    'status' => $log['status'],
                    'severity' => $log['severity'],
                    'entity_type' => $log['operation'] === 'order_submission' ? 'Order' : null,
                    'entity_id' => $log['operation'] === 'order_submission' ? ($this->orders[0]->id ?? null) : null,
                    'error_message' => $log['status'] === 'failed' ? 'Demo provider timeout for portfolio sync.' : null,
                    'request' => ['demo' => true, 'operation' => $log['operation']],
                    'response' => ['demo' => true, 'status' => $log['status']],
                    'metadata' => ['market' => 'NGX', 'market_open' => true],
                    'started_at' => now()->subMinutes(60 - $i * 5),
                    'completed_at' => now()->subMinutes(59 - $i * 5),
                ]
            );
        }

        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'demo_market_status'],
            ['value' => json_encode(['NGX' => 'open', 'GLOBAL' => 'open', 'CRYPTO' => 'open']), 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
