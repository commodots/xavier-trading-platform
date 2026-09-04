<?php

namespace App\Jobs;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\FixedIncomeProviderManager;
use App\Services\FixedIncome\ProviderLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileFixedIncomeProviders
    implements ShouldQueue
{
    use Queueable;

    public function handle(
        FixedIncomeProviderManager $manager,
        ProviderLogService $logger
    ): void {
        FixedIncomeInvestment::query()
            ->where('execution_mode', 'automated')
            ->whereNotNull('provider_reference')
            ->whereIn('status', [
                'pending_execution',
                'failed',
            ])
            ->chunkById(50, function ($investments) use (
                $manager,
                $logger
            ) {
                foreach ($investments as $investment) {

                    try {

                        $result =
                            $manager->status(
                                $investment
                            );

                        $logger->log(
                            $investment,
                            'status_reconciliation',
                            [
                                'status' =>
                                    $result['status']
                                        ?? 'unknown',

                                'provider_reference' =>
                                    $result['provider_reference']
                                        ?? $investment->provider_reference,

                                'response_payload' =>
                                    $result,
                            ]
                        );

                        $providerStatus =
                            $result['status'] ?? null;

                        if (
                            $providerStatus === 'active'
                            && $investment->status !== 'active'
                        ) {
                            app(
                                \App\Services\FixedIncomeLifecycleService::class
                            )->activate(
                                $investment,
                                $result['provider_reference']
                                    ?? $investment->provider_reference
                            );
                        }

                    } catch (\Throwable $e) {

                        Log::warning(
                            'Fixed Income reconciliation failed',
                            [
                                'investment_id' =>
                                    $investment->id,
                                'error' =>
                                    $e->getMessage(),
                            ]
                        );
                    }
                }
            });
    }
}