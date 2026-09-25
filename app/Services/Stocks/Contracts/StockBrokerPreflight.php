<?php

namespace App\Services\Stocks\Contracts;

interface StockBrokerPreflight
{
    public function assertReadyForSubmission(array $data): void;
}
