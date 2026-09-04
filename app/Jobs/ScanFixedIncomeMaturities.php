<?php

namespace App\Jobs;

use App\Models\FixedIncomeInvestment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanFixedIncomeMaturities implements ShouldQueue
{
    use Queueable;

    public function handle(): void
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
                        $investment->update([
                            'status' => 'maturing',
                            'last_status_at' => now(),
                        ]);
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