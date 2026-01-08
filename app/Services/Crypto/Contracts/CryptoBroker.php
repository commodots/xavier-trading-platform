<?php

namespace App\Services\Crypto\Contracts;

interface CryptoBroker
{
    public function placeOrder(array $data): array;
    public function getPortfolio(int $userId): array;
}
