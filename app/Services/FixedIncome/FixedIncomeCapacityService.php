<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeProduct;
use App\Models\FixedIncomeInvestment;
use RuntimeException;

class FixedIncomeCapacityService
{
    public function ensureAvailable(
        FixedIncomeProduct $product,
        float $amount
    ): void {
        if ($product->maximum_capacity === null) {
            return;
        }

        $current = FixedIncomeInvestment::query()
            ->where(
                'fixed_income_product_id',
                $product->id
            )
            ->whereNotIn('status', [
                'rejected',
                'cancelled',
                'redeemed',
            ])
            ->sum('principal_amount');

        if (
            ($current + $amount)
            > (float) $product->maximum_capacity
        ) {
            throw new RuntimeException(
                'This Fixed Income product has insufficient remaining capacity.'
            );
        }
    }
}
