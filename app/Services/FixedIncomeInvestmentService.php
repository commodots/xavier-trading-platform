<?php

namespace App\Services;

use App\Jobs\SubmitFixedIncomeInvestment;
use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProduct;
use App\Models\FixedIncomeTransaction;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FixedIncomeInvestmentService
{
    public function createFromWallet(
        $user,
        FixedIncomeProduct $product,
        float $amount
    ): FixedIncomeInvestment {

        $investment = DB::transaction(function () use (
            $user,
            $product,
            $amount
        ) {

            /** Re-check everything inside the transaction.* Never rely only on frontend validation.*/
            $this->validateProduct($product);

            if (! $product->acceptsAmount($amount)) {
                throw new RuntimeException(
                    'Investment amount is outside the allowed limits.'
                );
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
            $interest = $this->calculateInterest(
                $product,
                $amount
            );

            $maturityAmount = $amount + $interest;

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
            $status = $product->execution_mode === 'automated'
              ? 'pending_execution'
              : 'pending_execution';

            /** Create the actual Fixed Income investment.*/
            $investment = FixedIncomeInvestment::create([
                'user_id' => $user->id,
                'fixed_income_product_id' => $product->id,
                'reference' => $reference,

                'principal_amount' => $amount,
                'currency' => $product->currency,

                'interest_rate' => $product->interest_rate,
                'rate_type' => $product->rate_type,

                'expected_interest' => $interest,
                'expected_maturity_amount' => $maturityAmount,

                'status' => $status,

                'investment_date' => $investmentDate,

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

            /* * Investment-specific transaction history. */
            FixedIncomeTransaction::create([
                'fixed_income_investment_id' => $investment->id,

                'user_id' => $user->id,

                'type' => 'investment_funded',

                'amount' => $amount,

                'currency' => $product->currency,

                'status' => 'completed',

                'reference' => $reference,

                'transaction_id' => $transaction->id,

                'ledger_id' => $ledger->id,

                'metadata' => [
                    'funding_method' => 'wallet',
                ],
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
