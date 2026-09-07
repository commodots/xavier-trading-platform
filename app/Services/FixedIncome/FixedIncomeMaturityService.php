<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeTransaction;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FixedIncomeMaturityService
{
    public function mature(
        FixedIncomeInvestment $investment
    ): FixedIncomeInvestment {
        return DB::transaction(function () use ($investment) {

            $investment = FixedIncomeInvestment::query()
                ->lockForUpdate()
                ->findOrFail($investment->id);

            /** Idempotency:
             * Never pay a redeemed investment twice.
             */
            if ($investment->status === 'redeemed') {
                return $investment->fresh();
            }

            if (! in_array(
                $investment->status,
                ['active', 'maturing', 'matured']
            )) {
                throw new RuntimeException(
                    'Investment cannot be matured from its current status.'
                );
            }

            if (
                $investment->status !== 'matured'
                && (
                    ! $investment->maturity_date
                    || $investment->maturity_date->isFuture()
                )
            ) {
                throw new RuntimeException(
                    'Investment cannot be matured before its maturity date.'
                );
            }

            /**
             * If already matured, do not create another payout.
             */
            if (
                $investment->status === 'matured'
                && $investment->redeemed_at
            ) {
                return $investment->fresh();
            }

            $startDate = $investment->execution_date
                ?? $investment->investment_date
                ?? now();

            $endDate = $investment->maturity_date
                ?? now();

            $calculation = app(
                FixedIncomeReturnCalculator::class
            )->calculate(
                $investment->product,
                (float) $investment->principal_amount,
                $startDate,
                $endDate
            );

            $interest = round(
                $calculation['interest'],
                2
            );

            $principal = round(
                (float) $investment->principal_amount,
                2
            );

            $maturityAmount = round(
                $principal + $interest,
                2
            );

            /**
             * Locate and lock the user's wallet.
             */
            $wallet = Wallet::query()
                ->where('user_id', $investment->user_id)
                ->where('currency', $investment->currency)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException(
                    "No {$investment->currency} wallet exists."
                );
            }

            /**
             * The principal is already locked.
             *
             * Release:
             * locked principal → cleared principal
             */
            $reservedAmount = round(
                (float) $investment->reserved_amount,
                2
            );

            if ($reservedAmount > 0) {
                $wallet->releaseReservation(
                    $reservedAmount
                );
            }

            /**
             * Interest is new money.
             *
             * Add only the interest to cleared balance.
             */
            if ($interest > 0) {
                $wallet->credit(
                    $interest,
                    'cleared'
                );
            }

            $wallet->refreshBalance();

            $reference = 'FI-REDEEM-'
                .now()->format('Ym')
                .'-'
                .strtoupper(Str::random(8));

            /**
             * Existing Xavier financial transaction.
             */
            $transaction = NewTransaction::create([
                'user_id' => $investment->user_id,
                'type' => 'fixed_income_redemption',
                'amount' => $maturityAmount,
                'currency' => $investment->currency,
                'status' => 'completed',
                'charge' => 0,
                'net_amount' => $maturityAmount,
                'meta' => [
                    'reference' => $reference,
                    'fixed_income_investment_id' => $investment->id,
                    'fixed_income_reference' => $investment->reference,
                    'principal' => $principal,
                    'interest' => $interest,
                    'days' => $calculation['days'],
                ],
            ]);

            /**
             * Existing Xavier ledger.
             */
            $ledger = Ledger::create([
                'user_id' => $investment->user_id,
                'currency' => $investment->currency,
                'amount' => $maturityAmount,
                'type' => 'FIXED_INCOME_REDEMPTION',
                'status' => 'completed',
                'reference' => $reference,
                'meta' => [
                    'investment_id' => $investment->id,
                    'transaction_id' => $transaction->id,
                    'principal' => $principal,
                    'interest' => $interest,
                ],
                'is_platform' => false,
            ]);

            /**
             * Fixed Income-specific transaction history.
             */
            FixedIncomeTransaction::create([
                'fixed_income_investment_id' => $investment->id,

                'user_id' => $investment->user_id,

                'type' => 'investment_redeemed',

                'amount' => $maturityAmount,

                'currency' => $investment->currency,

                'status' => 'completed',

                'reference' => $reference,

                'transaction_id' => $transaction->id,

                'ledger_id' => $ledger->id,

                'metadata' => [
                    'principal' => $principal,
                    'interest' => $interest,
                    'days' => $calculation['days'],
                ],
            ]);

            if ($investment->status === 'active') {
                app(FixedIncomeStateManager::class)->transition($investment, 'maturing');
            }

            if ($investment->status === 'maturing') {
                app(FixedIncomeStateManager::class)->transition($investment, 'matured');
            }

            app(FixedIncomeStateManager::class)->transition($investment, 'redeemed');

            $investment->update([
                'actual_interest' => $interest,
                'actual_maturity_amount' => $maturityAmount,

                'redeemed_at' => now(),

                'reserved_amount' => 0,
            ]);

            app(FixedIncomeNotificationService::class)->send($investment, 'redeemed');

            return $investment->fresh();
        });
    }
}
