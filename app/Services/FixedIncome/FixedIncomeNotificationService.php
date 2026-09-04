<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeInvestment;
use App\Notifications\FixedIncomeInvestmentNotification;

class FixedIncomeNotificationService
{
    public function send(
        FixedIncomeInvestment $investment,
        string $event
    ): void {
        $investment->loadMissing('product');

        $investment->user?->notify(
            new FixedIncomeInvestmentNotification(
                $investment,
                $event
            )
        );
    }
}