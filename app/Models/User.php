<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
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
        'country',
        'next_of_kin',
        'next_of_kin_phone',
        'next_of_kin_email',
        'kyc_status',
        'trading_mode',
    ];

    protected $guard_name = 'api'; // For sanctum API guards

    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dob' => 'date',
    ];

    //  Relationship: One User has one Wallet
    public function wallet()
    {
        return $this->hasMany(\App\Models\Wallet::class);
    }

    //  Relationship: One User has one KYC Record
    public function kyc()
    {
        // return $this->hasOne(Kyc::class);
        return $this->hasOne(KycProfile::class, 'user_id');
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

    protected function profileImage(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value ? asset('storage/'.$value) : asset('images/user.png'),
        );
    }

    public function linkedAccounts()
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function holdings()
    {
        return $this->hasMany(Portfolio::class);
    }

    public function activities()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Get FX transactions for this user
     */
    public function isStaff()
    {
        return $this->hasAnyRole(['admin', 'accounts', 'manager', 'compliance', 'support']);
    }

    /**
     * Get FX wallet for a currency
     */
   
public function fxWallet(string $currency)
{
    return $this->wallet()->firstOrCreate(
        ['currency' => $currency],
        ['ngn_cleared' => 0, 'ngn_uncleared' => 0, 'usd_cleared' => 0, 'usd_uncleared' => 0]
    );
}

    /**
     * Check if the user's email has been verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Mark the user's email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        if ($this->hasVerifiedEmail()) {
            return false;
        }

        $this->forceFill(['email_verified_at' => $this->freshTimestamp()])->save();

        return true;
    }
}
