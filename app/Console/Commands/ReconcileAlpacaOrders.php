<?php

namespace App\Console\Commands;

use App\Models\ProviderSyncLog;
use App\Services\Stocks\AlpacaOrderReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileAlpacaOrders extends Command
{
    protected $signature = 'alpaca:reconcile-orders';

    protected $description = 'Reconcile Xavier Global stock orders with Alpaca';

    public function handle(
        AlpacaOrderReconciliationService $service
    ): int {
        if (filter_var(
            config('services.alpaca.mock', true),
            FILTER_VALIDATE_BOOL
        )) {
            $this->info(
                'Alpaca mock mode enabled; no live provider orders are read.'
            );
        }

        $startedAt = now();

        try {
            $result = $service->reconcile();

            ProviderSyncLog::create([
                'provider' => 'alpaca',
                'operation' => 'reconcile-orders',
                'status' => 'success',
                'reference' => 'alpaca',
                'response' => $result,
                'started_at' => $startedAt,
                'completed_at' => now(),
            ]);

            $this->info(
                'Orders: '.$result['orders']
                .', new trades: '.$result['trades']
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            ProviderSyncLog::create([
                'provider' => 'alpaca',
                'operation' => 'reconcile-orders',
                'status' => 'failed',
                'reference' => 'alpaca',
                'error_message' => $exception->getMessage(),
                'started_at' => $startedAt,
                'completed_at' => now(),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
