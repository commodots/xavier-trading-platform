<?php

namespace App\Services\Payments\Contracts;

interface PaymentGateway
{
    public function transfer(array $data): array;

    public function createVirtualAccount(array $data): array;

    public function getBalance(): array;
}
