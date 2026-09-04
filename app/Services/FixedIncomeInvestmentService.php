<?php

namespace App\Services;

use App\Jobs\SubmitFixedIncomeInvestment;
use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProduct;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\Wallet;
use App\Services\FixedIncome\FixedIncomeCapacityService;
use App\Services\FixedIncome\FixedIncomeReturnCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FixedIncomeInvestmentService
{
    public function createFromWallet(
        $user,
        FixedIncomeProduct $product,
        float $amount,
        ?string $idempotencyKey = null
    ): FixedIncomeInvestment {

        $investment = DB::transaction(function () use (
            $user,
            $product,
            $amount,
            $idempotencyKey
        ) {

            if (
                method_exists($user, 'isSuspended') &&
                $user->isSuspended()
            ) {
                throw new RuntimeException(
                    'This account is currently suspended.'
                );
            }

            $product = FixedIncomeProduct::query()
                ->lockForUpdate()
                ->findOrFail($product->id);

            if ($idempotencyKey) {
                $existing = FixedIncomeInvestment::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            /** Re-check everything inside the transaction. */
            $this->validateProduct($product);

            if (! $product->acceptsAmount($amount)) {
                throw new RuntimeException(
                    'Investment amount is outside the allowed limits.'
                );
            }

            app(FixedIncomeCapacityService::class)->ensureAvailable(
                $product,
                $amount
            );

            if ($product->maximum_user_capacity !== null) {
                $userExposure = FixedIncomeInvestment::query()
                    ->where('user_id', $user->id)
                    ->where('fixed_income_product_id', $product->id)
                    ->whereNotIn('status', [
                        'rejected',
                        'cancelled',
                        'redeemed',
                    ])
                    ->sum('principal_amount');

                if (
                    ((float) $userExposure + $amount) >
                    (float) $product->maximum_user_capacity
                ) {
                    throw new RuntimeException(
                        'This investment would exceed your maximum allocation for this product.'
                    );
                }
            }

            /** Locate the user's wallet in the product currency.*/
            $wallet = Wallet::where('user_id', $user->id)
                ->where('currency', $product->currency)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException(
                    "No {$product->currency} wallet exists for this account."
                );
            }

            $available = $wallet->getClearedBalance();

            if ($available < $amount) {
                throw new RuntimeException(
                    'Insufficient cleared wallet balance.'
                );
            }

            /** Calculate expected return.*/
            $calculation = app(
                FixedIncomeReturnCalculator::class
            )->calculate(
                $product,
                $amount,
                now(),
                $product->tenor_days
                    ? now()->copy()->addDays($product->tenor_days)
                    : null
            );

            $interest = $calculation['interest'];
            $maturityAmount = $calculation['maturity_amount'];

            $investmentDate = now();

            $maturityDate = $product->tenor_days
                ? $investmentDate->copy()->addDays(
                    $product->tenor_days
                )
                : null;

            $reference = $this->generateReference();

            /** Reserve the money first.We use Xavier's existing wallet reservation mechanism instead of directly modifying columns.*/
            $wallet->reserve($amount);

            /* * Determine initial execution status. */
            $status = 'pending_execution';

            /** Create the actual Fixed Income investment.*/
            $investment = FixedIncomeInvestment::create([
                'user_id' => $user->id,
                'fixed_income_product_id' => $product->id,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,

                'principal_amount' => $amount,
                'reserved_amount' => $amount,
                'currency' => $product->currency,

                'interest_rate' => $product->interest_rate,
                'rate_type' => $product->rate_type,

                'expected_interest' => round($interest, 2),
                'expected_maturity_amount' => round($maturityAmount, 2),

                'status' => $status,

                'investment_date' => $investmentDate,
                'funded_at' => $investmentDate,
                'last_status_at' => $investmentDate,

                'maturity_date' => $maturityDate,

                'funding_method' => 'wallet',

                'execution_mode' => $product->execution_mode,

                'provider' => $product->provider,

                'reinvestment_enabled' => false,
            ]);

            /** Create Xavier's live financial transaction.*/
            $transaction = NewTransaction::create([
                'user_id' => $user->id,
                'type' => 'fixed_income_investment',
                'amount' => $amount,
                'currency' => $product->currency,
                'status' => 'completed',
                'net_amount' => $amount,
                'charge' => 0,
                'meta' => [
                    'fixed_income_investment_id' => $investment->id,

                    'fixed_income_reference' => $reference,

                    'product_id' => $product->id,

                    'funding_method' => 'wallet',
                ],
            ]);

            /** Create ledger entry.*/
            $ledger = Ledger::create([
                'user_id' => $user->id,
                'currency' => $product->currency,
                'amount' => $amount,
                'type' => 'FIXED_INCOME_INVESTMENT',
                'status' => 'completed',
                'reference' => $reference,
                'meta' => [
                    'investment_id' => $investment->id,

                    'transaction_id' => $transaction->id,

                    'product_id' => $product->id,
                ],
                'is_platform' => false,
            ]);

            return $investment->fresh();
        });

        if ($investment->execution_mode === 'automated') {
            SubmitFixedIncomeInvestment::dispatch($investment->id)->afterCommit();
        }

        return $investment;
    }

    private function validateProduct(
        FixedIncomeProduct $product
    ): void {

        if ($product->status !== 'active') {
            throw new RuntimeException(
                'This Fixed Income product is not active.'
            );
        }

        $today = now()->startOfDay();

        if (
            $product->start_date &&
            $today->lt(
                $product->start_date->startOfDay()
            )
        ) {
            throw new RuntimeException(
                'Investment subscriptions have not started.'
            );
        }

        if (
            ! $product->open_ended &&
            $product->end_date &&
            $today->gt(
                $product->end_date->endOfDay()
            )
        ) {
            throw new RuntimeException(
                'Investment subscriptions have ended.'
            );
        }
    }

    private function calculateInterest(
        FixedIncomeProduct $product,
        float $amount
    ): float {

        if (
            ! $product->interest_rate ||
            ! $product->tenor_days
        ) {
            return 0;
        }

        return $amount
            * ($product->interest_rate / 100)
            * ($product->tenor_days / 365);
    }

    private function generateReference(): string
    {
        do {
            $reference =
                'FI-'.
                now()->format('Ym').
                '-'.
                strtoupper(Str::random(8));
        } while (
            FixedIncomeInvestment::where(
                'reference',
                $reference
            )->exists()
        );

        return $reference;
    }
}
