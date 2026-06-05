<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\KycService;

class KycResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'id_type' => $this->id_type,
            'id_value' => $this->id_value,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'photo' => $this->photo,

            
            'bvn' => $this->bvn ? KycService::maskPii($this->bvn) : null,
            'nin' => $this->nin ? KycService::maskPii($this->nin) : null,

            'address' => $this->address,
            'status' => $this->status ?? 'pending', 
            'level' => $this->level ?? 'none',   
            'tier' => (int) ($this->tier ?? 0),     
            'daily_limit' => $this->daily_limit ?? 500000,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}