<?php

namespace App\Services\Stocks\Contracts;

interface StockBroker
{
    public function buy(array $data): array;

    public function sell(array $data): array;

    public function portfolio(int $userId): array;

    public function history(int $userId): array;
}
