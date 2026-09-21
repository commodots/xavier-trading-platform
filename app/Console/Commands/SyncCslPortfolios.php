<?php

namespace App\Console\Commands;

use App\Models\ProviderAccount;
use App\Models\ProviderSyncLog;
use App\Services\CSL\CslPortfolioSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCslPortfolios extends Command
{
    protected $signature = 'csl:sync-portfolios';

    protected $description = 'Synchronize CSL portfolios into Xavier';

    public function handle(CslPortfolioSyncService $service): int
    {
        if (filter_var(config('services.csl.mock', true), FILTER_VALIDATE_BOOL)) {
            $this->info('CSL mock mode enabled; using deterministic fixture responses.');
        }

        $accounts = ProviderAccount::query()
            ->where('provider', 'csl')
            ->where('status', 'active')
            ->whereNotNull('market_account_id')
            ->get();

        $failed = false;

        foreach ($accounts as $account) {
            $startedAt = now();

            try {
                $count = $service->sync($account);

                ProviderSyncLog::create([
                    'provider' => 'csl',
                    'operation' => 'sync-portfolios',
                    'status' => 'success',
                    'reference' => $account->market_account_id,
                    'response' => ['rows' => $count],
                    'started_at' => $startedAt,
                    'completed_at' => now(),
                ]);

                $this->info("Account {$account->market_account_id}: {$count} portfolios synchronized.");
            } catch (Throwable $exception) {
                $failed = true;
                ProviderSyncLog::create([
                    'provider' => 'csl',
                    'operation' => 'sync-portfolios',
                    'status' => 'failed',
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
