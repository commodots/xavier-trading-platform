<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeProduct;
use Carbon\Carbon;
use InvalidArgumentException;

class FixedIncomeReturnCalculator
{
    /**
     * Calculate expected/actual fixed-income return.
     *
     * Returns:
     * - days
     * - interest
     * - maturity_amount
     */
    public function calculate(
        FixedIncomeProduct $product,
        float $principal,
        Carbon $startDate,
        ?Carbon $endDate = null
    ): array {
        if ($principal < 0) {
            throw new InvalidArgumentException(
                'Principal amount cannot be negative.'
            );
        }

        $endDate ??= $product->tenor_days
            ? $startDate->copy()->addDays($product->tenor_days)
            : null;

        if (! $endDate) {
            return [
                'days' => 0,
                'interest' => 0.0,
                'maturity_amount' => round($principal, 2),
            ];
        }

        $days = (int) round(
            $startDate->diffInSeconds($endDate) / 86400
        );

        $rate = (float) ($product->interest_rate ?? 0);

        if ($rate <= 0 || $days <= 0) {
            return [
                'days' => $days,
                'interest' => 0.0,
                'maturity_amount' => round($principal, 2),
            ];
        }

        $dayCount = $this->resolveDayCount(
            $product->day_count_basis
        );

        $method = $product->calculation_method
            ?: 'simple_interest';

        $interest = match ($method) {
            'simple_interest' => $this->simpleInterest(
                $principal,
                $rate,
                $days,
                $dayCount
            ),

            'compound_interest' => $this->compoundInterest(
                $principal,
                $rate,
                $days,
                $dayCount
            ),

            default => throw new InvalidArgumentException(
                "Unsupported Fixed Income calculation method: {$method}"
            ),
        };

        $interest = round($interest, 2);

        return [
            'days' => $days,
            'interest' => $interest,
            'maturity_amount' => round(
                $principal + $interest,
                2
            ),
        ];
    }

    private function simpleInterest(
        float $principal,
        float $rate,
        int $days,
        int $dayCount
    ): float {
        return $principal
            * ($rate / 100)
            * ($days / $dayCount);
    }

    private function compoundInterest(
        float $principal,
        float $rate,
        int $days,
        int $dayCount
    ): float {
        $periodRate = $rate / 100;

        $amount = $principal * pow(
            1 + ($periodRate / $dayCount),
            $days
        );

        return $amount - $principal;
    }

    private function resolveDayCount(
        ?string $basis
    ): int {
        return match ($basis) {
            'actual_360' => 360,
            'actual_366' => 366,
            'actual_365', null, '' => 365,

            default => throw new InvalidArgumentException(
                "Unsupported day-count basis: {$basis}"
            ),
        };
    }
}
