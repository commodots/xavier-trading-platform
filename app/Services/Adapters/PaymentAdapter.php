<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymentAdapter extends BaseServiceAdapter
{
    public function processPayment(array $payload)
    {
        $authHeaders = array_merge(
            $this->connection->headers ?? [],
            ['Authorization' => 'Bearer ' . ($this->connection->credentials['api_key'] ?? '')]
        );
        
       
        return Http::withHeaders($authHeaders)
            ->post($this->connection->base_url . '/process-payment', $payload);
    }
    
    public function getTransactionStatus(string $transactionId)
    {
        // Get authentication headers/credentials
        $authHeaders = array_merge(
            $this->connection->headers ?? [],
            ['Authorization' => 'Bearer ' . ($this->connection->credentials['api_key'] ?? '')]
        );
        
        // Make the API call
        return Http::withHeaders($authHeaders)
            ->get($this->connection->base_url . "/transactions/{$transactionId}");
    }

    // ... other payment-related methods (e.g., refund, verify)
}