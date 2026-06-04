<?php

namespace App\Exceptions;

use RuntimeException;

class FxRateUnavailableException extends RuntimeException
{
    public function __construct(string $from = 'USD', string $to = 'NGN')
    {
        parent::__construct("{$from}/{$to} FX rate is not configured or invalid. Cannot proceed.");
    }
}
