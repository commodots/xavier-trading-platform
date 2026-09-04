<?php

namespace App\Jobs;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\FixedIncomeStateManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanFixedIncomeMaturities implements ShouldQueue
{
    use Queueable;

    public function handle(FixedIncomeStateManager $stateManager): void
    {
        FixedIncomeInvestment::query()
            ->whereIn('status', [
                'active',
                'maturing',
            ])
            ->whereNotNull('maturity_date')
            ->where(
                'maturity_date',
                '<=',
                now()->addDays(7)
            )
            ->chunkById(100, function ($investments) {

                foreach ($investments as $investment) {

                    if (
                        $investment->status === 'active'
                    ) {
                        $stateManager->transition($investment, 'maturing');
                    }

                    if (
                        $investment->maturity_date
                        <= now()
                    ) {
                        ProcessFixedIncomeMaturity::dispatch(
                            $investment->id
                        );
                    }
                }
            });
    }
}
