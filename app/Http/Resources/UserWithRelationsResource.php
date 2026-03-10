<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWithRelationsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'email'        => $this->email,
            'trading_mode' => $this->trading_mode,
            'phone'        => $this->phone,
            'dob'          => $this->dob,
            'wallet'       => new WalletResource($this->whenLoaded('wallet')),
            'kyc'          => new KycResource($this->whenLoaded('kyc')),
            
            // Roles & Permissions
            'roles'        => method_exists($this, 'getRoleNames') ? $this->getRoleNames() : [],
            'permissions'  => method_exists($this, 'getAllPermissions') ? $this->getAllPermissions()->pluck('name') : [],
            
            // Trial Logic 
            'has_active_subscription' => $this->has_active_subscription,
            'on_trial'                => $this->on_trial, 
            'trial_started_at'        => $this->trial_started_at,
            'trial_expires_at'        => $this->trial_expires_at, 
            'trial_days_left'         => $this->trial_days_left,

            'created_at'   => $this->created_at,
            'token'        => $this->when(isset($this->token), $this->token),
        ];
    }
}