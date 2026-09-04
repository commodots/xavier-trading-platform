<?php

namespace App\Services;

use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeTransaction;
use App\Models\Wallet;
use App\Services\FixedIncome\FixedIncomeMaturityService;
use App\Services\FixedIncome\FixedIncomeNotificationService;
use App\Services\FixedIncome\FixedIncomeStateManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FixedIncomeLifecycleService
{
    public function activate(
        FixedIncomeInvestment $investment,
        ?string $providerReference = null
    ): FixedIncomeInvestment {

        return DB::transaction(function () use (
            $investment,
            $providerReference
        ) {

            $investment = FixedIncomeInvestment::where(
                'id',
                $investment->id
            )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! in_array(
                    $investment->status,
                    ['pending_execution', 'failed']
                )
            ) {
                throw new RuntimeException(
                    'Investment cannot be activated from its current status.'
                );
            }

            app(FixedIncomeStateManager::class)->transition(
                $investment,
                'active'
            );

            $investment->update([
                'execution_date' => now(),
                'provider_reference' => $providerReference,
            ]);

            FixedIncomeTransaction::create([
                'fixed_income_investment_id' => $investment->id,

                'user_id' => $investment->user_id,

                'type' => 'investment_activated',

                'amount' => $investment->principal_amount,

                'currency' => $investment->currency,

                'status' => 'completed',

                'reference' => 'FI-ACT-'.$investment->id,

                'metadata' => [
                    'provider_reference' => $providerReference,
                ],
            ]);

            app(FixedIncomeNotificationService::class)->send($investment, 'activated');

            return $investment->fresh();
        });
    }

    public function mature(
        FixedIncomeInvestment $investment
    ): FixedIncomeInvestment {
        return app(
            FixedIncomeMaturityService::class
        )->mature($investment);
    }

    public function reject(
        FixedIncomeInvestment $investment,
        string $reason
    ): FixedIncomeInvestment {

        return DB::transaction(function () use (
            $investment,
            $reason
        ) {

            $investment = FixedIncomeInvestment::where(
                'id',
                $investment->id
            )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $investment->status !==
                'pending_execution'
            ) {
                throw new RuntimeException(
                    'Only pending investments can be rejected.'
                );
            }

            $wallet = Wallet::where('user_id', $investment->user_id)
                ->where('currency', $investment->currency)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (float) $investment->reserved_amount;

            if ($amount <= 0) {
                $amount = (float) $investment->principal_amount;
            }

            $wallet->releaseReservation($amount);

            app(FixedIncomeStateManager::class)->transition(
                $investment,
                'rejected'
            );

            $investment->update([
                'reserved_amount' => 0,
                'metadata' => array_merge(
                    $investment->metadata ?? [],
                    [
                        'rejection_reason' => $reason,
                    ]
                ),
            ]);

            FixedIncomeTransaction::create([
                'fixed_income_investment_id' => $investment->id,

                'user_id' => $investment->user_id,

                'type' => 'investment_rejected',

                'amount' => $investment->principal_amount,

                'currency' => $investment->currency,

                'status' => 'completed',

                'reference' => 'FI-REJECT-'.$investment->id,

                'metadata' => [
                    'reason' => $reason,
                ],
            ]);

            app(FixedIncomeNotificationService::class)->send($investment, 'rejected');

            return $investment->fresh();
        });
    }

    public function cancel(
        FixedIncomeInvestment $investment
    ): FixedIncomeInvestment {
        return DB::transaction(function () use ($investment): FixedIncomeInvestment {
            $investment = FixedIncomeInvestment::query()
                ->lockForUpdate()
                ->findOrFail($investment->id);

            $wallet = Wallet::query()
                ->where('user_id', $investment->user_id)
                ->where('currency', $investment->currency)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (float) ($investment->reserved_amount ?: $investment->principal_amount);

            if ($amount > 0) {
                $wallet->releaseReservation($amount);
            }

            app(FixedIncomeStateManager::class)->transition(
                $investment,
                'cancelled'
            );

            $investment->update(['reserved_amount' => 0]);

            FixedIncomeTransaction::create([
                'fixed_income_investment_id' => $investment->id,
                'user_id' => $investment->user_id,
                'type' => 'investment_cancelled',
                'amount' => $investment->principal_amount,
                'currency' => $investment->currency,
                'status' => 'completed',
                'reference' => 'FI-CANCEL-'.$investment->id,
            ]);

            return $investment->fresh();
        });
    }
}
