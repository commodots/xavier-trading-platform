<?php

namespace App\Services\CSL;

class CslOrderResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $status,
        public readonly ?string $providerOrderId,
        public readonly ?string $remarks,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'status' => $this->status,
            'provider_order_id' => $this->providerOrderId,
            'remarks' => $this->remarks,
            'raw' => $this->raw,
        ];
    }
}
