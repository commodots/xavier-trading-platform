<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CryptoAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blockchain' => $this->blockchain, // e.g., 'TRON'
            'address' => $this->address,

            // Helper for the frontend to show a QR code
            // This uses a public API to generate a QR for the address
            'qr_code_url' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$this->address}",

            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
