<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NgxAdapter extends BaseServiceAdapter
{
    public function placeOrder($payload)
    {
        // Accesses the active connection's details dynamically
        return Http::withHeaders($this->connection->headers) 
            ->post($this->connection->base_url . '/orders', $payload); 
    }
    
}