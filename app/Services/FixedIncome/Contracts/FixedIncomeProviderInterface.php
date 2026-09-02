<?php

namespace App\Services\FixedIncome\Contracts;

use App\Models\FixedIncomeInvestment;

interface FixedIncomeProviderInterface
{
    public function submit(
        FixedIncomeInvestment $investment
    ): array;

    public function getStatus(
        FixedIncomeInvestment $investment
    ): array;

    public function cancel(
        FixedIncomeInvestment $investment
    ): array;

    public function redeem(
        FixedIncomeInvestment $investment
    ): array;
}
