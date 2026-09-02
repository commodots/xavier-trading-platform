<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\Contracts\FixedIncomeProviderInterface;
use App\Services\FixedIncome\Providers\ApiFixedIncomeProvider;
use App\Services\FixedIncome\Providers\ManualFixedIncomeProvider;
use RuntimeException;

class FixedIncomeProviderManager
{
    public function provider(
        FixedIncomeInvestment $investment
    ): FixedIncomeProviderInterface {

        if (
            $investment->execution_mode ===
            'manual'
        ) {
            return app(
                ManualFixedIncomeProvider::class
            );
        }

        if (
            $investment->execution_mode ===
            'automated'
        ) {
            return app(
                ApiFixedIncomeProvider::class
            );
        }

        throw new RuntimeException(
            'Unknown Fixed Income execution mode.'
        );
    }

    public function submit(
        FixedIncomeInvestment $investment
    ): array {

        return $this
            ->provider($investment)
            ->submit($investment);
    }

    public function status(
        FixedIncomeInvestment $investment
    ): array {

        return $this
            ->provider($investment)
            ->getStatus($investment);
    }

    public function cancel(
        FixedIncomeInvestment $investment
    ): array {

        return $this
            ->provider($investment)
            ->cancel($investment);
    }

    public function redeem(
        FixedIncomeInvestment $investment
    ): array {

        return $this
            ->provider($investment)
            ->redeem($investment);
    }
}
