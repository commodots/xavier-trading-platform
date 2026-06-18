<?php

namespace App\Contracts;

interface FxProviderInterface
{
    public function quote(string $from, string $to, float $amount): array;

    public function convert(string $from, string $to, float $amount): array;
}