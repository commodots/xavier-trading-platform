<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // ✅ correct import
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'dob',
        'password',
        'email_verified_at',
    ];

    protected $guard_name = 'api'; // For sanctum API guards

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dob' => 'date',
    ];

    // ✅ Relationship: One User has one Wallet
    public function wallet()
    {
        return $this->hasMany(\App\Models\Wallet::class);
    }

    // ✅ Relationship: One User has one KYC Record
    public function kyc()
    {
        //return $this->hasOne(Kyc::class);
        return $this->hasOne(\App\Models\UserKyc::class, 'user_id');
    }
    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class);
    }
    public function transactions()
    {
        return $this->hasMany(\App\Models\Transaction::class);
    }

    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = $value;

        // Automatically update the combined 'name' field
        $lastName = $this->attributes['last_name'] ?? '';
        $this->attributes['name'] = trim("{$value} {$lastName}");
    }
    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = $value;

        // Automatically update the combined 'name' field
        $firstName = $this->attributes['first_name'] ?? '';
        $this->attributes['name'] = trim("{$firstName} {$value}");
    }
    protected function google2faSecret(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }
}
