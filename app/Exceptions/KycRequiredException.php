<?php

namespace App\Exceptions;

use RuntimeException;

class KycRequiredException extends RuntimeException
{
    public function __construct(int $requiredLevel = 1)
    {
        parent::__construct("KYC verification level {$requiredLevel} is required to perform this action.");
    }
}
