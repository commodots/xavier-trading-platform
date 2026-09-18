<?php

namespace App\Services\CSL;

use RuntimeException;

class CslMockTransport
{
    public function response(string $operation, array $payload = []): array
    {
        return match ($operation) {
            'instruments' => $this->fixture('instruments'),
            'market_status' => $this->fixture('market_status'),
            'quote' => $this->fixture('quote'),
            'buy' => $this->fixture('buy_accepted'),
            'sell' => $this->fixture('sell_accepted'),
            'open_orders' => $this->fixture('open_orders'),
            'partial_fill' => $this->fixture('partial_fill'),
            'executed_order' => $this->fixture('executed_order'),
            'cancelled_order' => $this->fixture('cancelled_order'),
            default => throw new RuntimeException("Unknown CSL mock operation: {$operation}"),
        };
    }

    protected function fixture(string $name): array
    {
        $path = base_path('tests/Fixtures/CSL/'.$name.'.json');

        if (! file_exists($path)) {
            throw new RuntimeException("CSL mock fixture not found: {$name}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Invalid CSL fixture: {$name}");
        }

        return $data;
    }
}
