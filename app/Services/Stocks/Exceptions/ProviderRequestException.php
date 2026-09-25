<?php

namespace App\Services\Stocks\Exceptions;

use RuntimeException;

class ProviderRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $ambiguous,
        public readonly int $statusCode = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
