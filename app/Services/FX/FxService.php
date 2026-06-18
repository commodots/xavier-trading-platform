<?php

namespace App\Services\Fx;

class FxService
{
    public function __construct(
        protected FxProviderResolver $resolver
    ) {}

    public function quote(string $from, string $to, float $amount): array
    {
        return $this->resolver->resolve()->quote($from, $to, $amount);
    }

    public function convert(string $from, string $to, float $amount): array
    {
        return $this->resolver->resolve()->convert($from, $to, $amount);
    }

    public function getProviderName(): string
    {
        $setting = \App\Models\FxSetting::first();
        return $setting ? $setting->provider : 'manual';
    }
}