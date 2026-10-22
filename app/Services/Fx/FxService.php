<?php

namespace App\Services\Fx;

use App\Models\FxSetting;
use App\Contracts\FxProviderInterface;

class FxService
{
    public function __construct(
        protected FxProviderResolver $resolver
    ) {}

    public function quote(string $from, string $to, float $amount): array
    {
        return $this->provider()->quote($from, $to, $amount);
    }

    public function convert(string $from, string $to, float $amount, ?string $reference = null, ?array $quote = null): array
    {
        return $this->provider()->convert($from, $to, $amount, $reference, $quote);
    }

    public function provider(): FxProviderInterface
    {
        return $this->resolver->resolve();
    }

    public function getProviderName(): string
    {
        $setting = FxSetting::first();

        return $setting ? $setting->provider : 'manual';
    }
}
