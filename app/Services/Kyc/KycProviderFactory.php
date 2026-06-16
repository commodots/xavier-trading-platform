<?php

namespace App\Services\Kyc;

use App\Services\Kyc\Contracts\KycProviderInterface;
use App\Services\Kyc\Providers\DojahProvider;
use App\Services\Kyc\Providers\QoreIdProvider;

class KycProviderFactory
{
    /**
     * Create the appropriate KYC provider based on config.
     */
    public static function make(): KycProviderInterface
    {
        return match (config('kyc.provider', 'dojah')) {
            default => app(DojahProvider::class),
            'qoreid'=> app(QoreIdProvider::class),
        };
    }
}