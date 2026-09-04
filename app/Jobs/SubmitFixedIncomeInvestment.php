<?php

namespace App\Jobs;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\FixedIncomeNotificationService;
use App\Services\FixedIncome\FixedIncomeProviderManager;
use App\Services\FixedIncome\FixedIncomeStateManager;
use App\Services\FixedIncome\ProviderLogService;
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
        FixedIncomeProviderManager $providerManager,
        ProviderLogService $logger,
        FixedIncomeStateManager $stateManager,
        FixedIncomeNotificationService $notifications
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

            $logger->log($investment, 'submit', [
                'status' => $result['status'] ?? 'submitted',
                'provider_reference' => $result['provider_reference'] ?? null,
                'response_payload' => $result,
            ]);

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

            $stateManager->transition($investment, 'failed');
            $notifications->send($investment, 'failed');
            $logger->log($investment, 'submit', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $investment->update([
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
