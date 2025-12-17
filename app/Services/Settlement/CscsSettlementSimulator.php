<?php

namespace App\Services\Settlement;

use App\Models\Trade;
use App\Services\Portfolio\PortfolioService;

class CscsSettlementSimulator
{
    public function settle(Trade $trade)
    {
        PortfolioService::postTrade($trade);

        $trade->settled_at = now();
        $trade->save();
    }
}
