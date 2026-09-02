<?php

namespace App\Services\FixedIncome\Providers;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\Contracts\FixedIncomeProviderInterface;
use RuntimeException;

class ApiFixedIncomeProvider implements FixedIncomeProviderInterface
{
    public function submit(
        FixedIncomeInvestment $investment
    ): array {

        /*
         * Do NOT call an external API yet.
         *
         * This class becomes the adapter for the selected
         * provider after the actual provider API has been
         * confirmed.
         */

        throw new RuntimeException(
            'Automated Fixed Income provider is not configured.'
        );
    }

    public function getStatus(
        FixedIncomeInvestment $investment
    ): array {

        throw new RuntimeException(
            'Automated Fixed Income provider is not configured.'
        );
    }

    public function cancel(
        FixedIncomeInvestment $investment
    ): array {

        throw new RuntimeException(
            'Automated Fixed Income provider is not configured.'
        );
    }

    public function redeem(
        FixedIncomeInvestment $investment
    ): array {

        throw new RuntimeException(
            'Automated Fixed Income provider is not configured.'
        );
    }
}
