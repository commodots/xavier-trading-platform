<?php

namespace App\Jobs;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\FixedIncomeProviderManager;
use App\Services\FixedIncomeLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SubmitFixedIncomeInvestment implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $investmentId
    ) {}

    public function handle(
        FixedIncomeProviderManager $providerManager
    ): void {

        $investment =
            FixedIncomeInvestment::find(
                $this->investmentId
            );

        if (! $investment) {
            return;
        }

        if (
            $investment->status !==
            'pending_execution'
        ) {
            return;
        }

        try {

            $result =
                $providerManager->submit(
                    $investment
                );

            if (
                ! empty(
                    $result['provider_reference']
                )
            ) {
                $investment->update([
                    'provider_reference' => $result['provider_reference'],
                ]);
            }

            /*
             * If the provider immediately confirms
             * the investment, activate it.
             *
             * Otherwise leave it pending.
             */

            if (
                ($result['status'] ?? null)
                === 'active'
            ) {

                app(
                    FixedIncomeLifecycleService::class
                )->activate(
                    $investment,
                    $result['provider_reference']
                        ?? null
                );
            }

        } catch (\Throwable $e) {

            Log::error(
                'Fixed Income automated execution failed',
                [
                    'investment_id' => $investment->id,

                    'reference' => $investment->reference,

                    'error' => $e->getMessage(),
                ]
            );

            $investment->update([
                'status' => 'failed',

                'metadata' => array_merge(
                    $investment->metadata ?? [],
                    [
                        'execution_error' => $e->getMessage(),
                    ]
                ),
            ]);
        }
    }
}
