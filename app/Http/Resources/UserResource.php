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
            'first_name' => $this->first_name,
            'surname' => $this->surname,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'dob' => $this->dob,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,

            // Nested resources
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
            'kyc' => new KycResource($this->whenLoaded('kyc')),
        ];
    }
}
