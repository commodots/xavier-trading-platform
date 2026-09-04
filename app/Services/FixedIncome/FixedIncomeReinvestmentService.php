<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncomeInvestmentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FixedIncomeReinvestmentService
{
    public function reinvest(
        FixedIncomeInvestment $investment
    ): FixedIncomeInvestment {
        return DB::transaction(function () use ($investment) {

            $investment = FixedIncomeInvestment::query()
                ->lockForUpdate()
                ->findOrFail($investment->id);

            if ($investment->status !== 'redeemed') {
                throw new RuntimeException(
                    'Only redeemed investments can be reinvested.'
                );
            }

            if (! $investment->reinvestment_enabled) {
                throw new RuntimeException(
                    'Reinvestment is not enabled for this investment.'
                );
            }

            $product = $investment->product;

            if (! $product || ! $product->allow_reinvestment) {
                throw new RuntimeException(
                    'Reinvestment is not available for this product.'
                );
            }

            $principal = (float)
                $investment->actual_maturity_amount;

            $newInvestment = app(
                FixedIncomeInvestmentService::class
            )->createFromWallet(
                $investment->user,
                $product,
                $principal
            );

            $newInvestment->update([
                'reinvestment_enabled' => $product->allow_reinvestment,

                'metadata' => array_merge(
                    $newInvestment->metadata ?? [],
                    [
                        'reinvestment_from' => $investment->id,

                        'reinvestment_reference' => $investment->reference,
                    ]
                ),
            ]);

            $investment->update([
                'metadata' => array_merge(
                    $investment->metadata ?? [],
                    [
                        'reinvested_into' => $newInvestment->id,

                        'reinvestment_reference' => $newInvestment->reference,
                    ]
                ),
            ]);

            return $newInvestment->fresh();
        });
    }
}
