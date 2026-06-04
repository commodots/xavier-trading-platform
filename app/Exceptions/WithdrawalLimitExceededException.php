<?php

namespace App\Exceptions;

use RuntimeException;

class WithdrawalLimitExceededException extends RuntimeException
{
    public function __construct(string $message = 'Withdrawal exceeds your daily limit or you are in a cooldown period.')
    {
        parent::__construct($message);
    }
}
