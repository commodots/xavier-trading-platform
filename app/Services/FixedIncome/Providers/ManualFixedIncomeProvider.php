<?php

namespace App\Services\FixedIncome\Providers;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\Contracts\FixedIncomeProviderInterface;

class ManualFixedIncomeProvider implements FixedIncomeProviderInterface
{
    public function submit(
        FixedIncomeInvestment $investment
    ): array {

        return [
            'success' => true,
            'status' => 'pending_execution',
            'provider' => 'manual',
            'provider_reference' => null,
        ];
    }

    public function getStatus(
        FixedIncomeInvestment $investment
    ): array {

        return [
            'success' => true,
            'status' => $investment->status,
            'provider_reference' => $investment->provider_reference,
        ];
    }

    public function cancel(
        FixedIncomeInvestment $investment
    ): array {

        return [
            'success' => true,
            'status' => 'cancelled',
        ];
    }

    public function redeem(
        FixedIncomeInvestment $investment
    ): array {

        return [
            'success' => true,
            'status' => 'redeemed',
        ];
    }
}
