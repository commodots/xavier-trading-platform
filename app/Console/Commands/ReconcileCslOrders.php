<?php

namespace App\Console\Commands;

use App\Models\ProviderAccount;
use App\Models\ProviderSyncLog;
use App\Services\CSL\CslOrderReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileCslOrders extends Command
{
    protected $signature =
        'csl:reconcile-orders';

    protected $description =
        'Reconcile Xavier orders with CSL';

    public function handle(
        CslOrderReconciliationService $service
    ): int {

        $accounts = ProviderAccount::where(
            'provider',
            'csl'
        )
            ->where('status', 'active')
            ->whereNotNull('market_account_id')
            ->get();

        $failed = false;

        foreach ($accounts as $account) {
            $startedAt = now();

            try {
                $result = $service->reconcile(
                    $account->market_account_id
                );

                ProviderSyncLog::create([
                    'provider' => 'csl',
                    'operation' => 'reconcile-orders',
                    'status' => 'success',
                    'reference' => $account->market_account_id,
                    'response' => $result,
                    'started_at' => $startedAt,
                    'completed_at' => now(),
                ]);

                $this->info(
                    "Account {$account->market_account_id}: "
                    .json_encode($result)
                );

            } catch (Throwable $e) {
                $failed = true;
                ProviderSyncLog::create([
                    'provider' => 'csl',
                    'operation' => 'reconcile-orders',
                    'status' => 'failed',
                    'reference' => $account->market_account_id,
                    'error_message' => $e->getMessage(),
                    'started_at' => $startedAt ?? now(),
                    'completed_at' => now(),
                ]);

                $this->error(
                    "Account {$account->market_account_id}: "
                    .$e->getMessage()
                );
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
