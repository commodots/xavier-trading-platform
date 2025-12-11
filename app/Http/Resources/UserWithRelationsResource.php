<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWithRelationsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'    => $this->last_name,
            'email'      => $this->email,
            'phone'     => $this->phone,
            'dob'        => $this->dob,
            'wallet'     => new WalletResource($this->whenLoaded('wallet')),
            'kyc'        => new KycResource($this->whenLoaded('kyc')),
            'created_at' => $this->created_at,
            'token'      => $this->when(isset($this->token), $this->token),
        ];
    }
}
