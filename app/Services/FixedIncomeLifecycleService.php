<?php

namespace App\Services;

use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeTransaction;
use App\Models\Wallet;
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

            $investment->update([
                'status' => 'active',
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

            return $investment->fresh();
        });
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

            $wallet->releaseReservation($investment->principal_amount);

            $investment->update([
                'status' => 'rejected',
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

            return $investment->fresh();
        });
    }
}
