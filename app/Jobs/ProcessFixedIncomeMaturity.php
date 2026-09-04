<?php

namespace App\Jobs;

use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\FixedIncomeMaturityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFixedIncomeMaturity implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $investmentId
    ) {}

    public function handle(
        FixedIncomeMaturityService $maturityService
    ): void {
        $investment =
            FixedIncomeInvestment::find(
                $this->investmentId
            );

        if (! $investment) {
            return;
        }

        $maturityService->mature(
            $investment
        );
    }
}
