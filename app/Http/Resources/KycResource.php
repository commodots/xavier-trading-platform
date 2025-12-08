<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'bvn' => $this->bvn,
            'nin' => $this->nin,
            'address' => $this->address,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
