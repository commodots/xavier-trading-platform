<?php

namespace App\Console\Commands;

use App\Models\ProviderAccount;
use App\Models\ProviderSyncLog;
use App\Services\Stocks\AlpacaPortfolioSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncAlpacaData extends Command
{
    protected $signature = 'alpaca:sync-portfolios';

    protected $description = 'Synchronize dedicated Alpaca provider-account portfolios into Xavier';

    public function handle(AlpacaPortfolioSyncService $service): int
    {
        $accounts = ProviderAccount::query()
            ->where('provider', 'alpaca')
            ->where('status', 'active')
            ->whereNotNull('market_account_id')
            ->get();
        $failed = false;

        foreach ($accounts as $account) {
            $startedAt = now();

            try {
                $count = $service->sync($account);

                ProviderSyncLog::create([
                    'provider' => 'alpaca',
                    'operation' => 'sync-portfolios',
                    'entity_type' => 'provider_account',
                    'entity_id' => $account->id,
                    'status' => 'success',
                    'reference' => $account->market_account_id,
                    'response' => ['positions' => $count],
                    'started_at' => $startedAt,
                    'completed_at' => now(),
                ]);

                $this->info("Account {$account->market_account_id}: {$count} positions synchronized.");
            } catch (Throwable $exception) {
                $failed = true;

                ProviderSyncLog::create([
                    'provider' => 'alpaca',
                    'operation' => 'sync-portfolios',
                    'entity_type' => 'provider_account',
                    'entity_id' => $account->id,
                    'status' => 'failed',
                    'severity' => 'error',
                    'reference' => $account->market_account_id,
                    'error_message' => $exception->getMessage(),
                    'started_at' => $startedAt,
                    'completed_at' => now(),
                ]);

                $this->error("Account {$account->market_account_id}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
