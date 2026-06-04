<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(string $currency = 'NGN', float $required = 0, float $available = 0)
    {
        parent::__construct(
            "Insufficient cleared {$currency} balance. Required: {$required}, Available: {$available}."
        );
    }
}
