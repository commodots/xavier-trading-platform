<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'balance' => (float) $this->balance,
            'locked' => (float) $this->locked,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
