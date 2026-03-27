<?php

namespace App\Services\Adapters;

use Illuminate\Support\Facades\Http;

class CscsAdapter extends BaseServiceAdapter
{
    public function settle($payload)
    {
        // Accesses the active connection's details dynamically
        return Http::withHeaders($this->connection->headers)
            ->post($this->connection->base_url.'/settle', $payload);
    }

    public function getSettlementStatus($tradeId)
    {
        return Http::withHeaders($this->connection->headers)
            ->get($this->connection->base_url.'/settlement/'.$tradeId);
    }
}
