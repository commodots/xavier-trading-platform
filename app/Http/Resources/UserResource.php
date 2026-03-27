<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'dob' => $this->dob,
            'profile_image' => $this->profile_image,

            'trading_mode' => $this->trading_mode,

            // Subscription info for the UI
            'tier' => $this->current_tier,
            'on_trial' => $this->on_trial,

            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,

            // Nested resources
            'wallet' => WalletResource::collection($this->whenLoaded('wallet')),
            'kyc' => new KycResource($this->whenLoaded('kyc')),

            'crypto_addresses' => CryptoAddressResource::collection($this->whenLoaded('cryptoAddresses')),
        ];
    }
}
