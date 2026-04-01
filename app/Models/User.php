<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
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
        'gender',
        'address',
        'profile_image',
        'kyc_note',
        'password',
        'country',
        'next_of_kin',
        'next_of_kin_phone',
        'next_of_kin_email',
        'bank_name',
        'bank_account',
        'bvn',
        'nin',
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

    protected $appends = [
        'has_active_subscription',
        'on_trial',
        'trial_days_left',
        'trial_expires_at',
        'current_tier',
        'has_used_regular',
        'has_used_premium',
        'avatar',
        'kyc_verified',
    ];

    //  Relationship: One User has one Wallet
    public function wallet()
    {
        return $this->hasMany(\App\Models\Wallet::class);
    }

    /**
     * Get the demo wallets for the user.
     */
    public function demoWallet()
    {
        return $this->hasMany(\App\Models\Demo\DemoWallet::class);
    }

    //  Relationship: One User has one KYC Record
    public function kyc()
    {
        // return $this->hasOne(Kyc::class);
        return $this->hasOne(KycProfile::class, 'user_id');
    }

    /**
     * Determine if the user has a verified KYC profile.
     */
    public function getKycVerifiedAttribute(): bool
    {
        return $this->kyc()->where('status', 'verified')->exists();
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

    public function getAvatarAttribute()
    {
        // This will automatically use the profileImage accessor to get the full URL.
        return $this->profile_image;
    }

    protected function profileImage(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value ? Storage::url($value) : asset('images/user.png'),
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
    public function isStaff(): bool
    {
        return $this->hasAnyRole(['admin', 'accounts', 'manager', 'compliance', 'support'])
            || in_array($this->role, ['admin', 'accounts', 'manager', 'compliance', 'support'], true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->role === 'admin';
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

    /**
     * Subscription service
     */
    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    // Helper method to check active status easily
    public function hasActiveSubscription()
    {
        return $this->subscriptions()
            ->where('expires_at', '>', now())
            ->whereIn('status', ['active', 'trial'])
            ->exists();
    }

    public function getHasActiveSubscriptionAttribute()
    {
        return $this->hasActiveSubscription();
    }

    protected $attributes = [
        'trading_mode' => 'live',
    ];

    /**
     * Helper to get the current active trial subscription, cached for the request.
     */
    public function trialSubscription()
    {
        // Using a dynamic property to cache the result for the current request
        if (! isset($this->_trialSubscription)) {
            $this->_trialSubscription = $this->subscriptions()
                ->where('status', 'trial')
                ->where('expires_at', '>', now())
                ->latest('expires_at')
                ->first();
        }

        return $this->_trialSubscription;
    }

    public function onTrial(): bool
    {
        return (bool) $this->trialSubscription();
    }

    public function getOnTrialAttribute()
    {
        return $this->onTrial();
    }

    /**
     * Send the customized email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }

    public function getTrialDaysLeftAttribute()
    {
        if ($trial = $this->trialSubscription()) {
            $diff = now()->diffInDays($trial->expires_at, false);

            return max(0, (int) $diff);
        }

        // If not on an active trial, return the default from settings
        $settings = \App\Models\SystemSetting::first();

        return (int) ($settings->trial_days ?? 7);
    }

    public function getTrialExpiresAtAttribute()
    {
        return $this->trialSubscription()?->expires_at?->toIso8601String();
    }

    public function getCurrentTierAttribute(): ?string
    {
        // Fetch all active subscriptions (Trial OR Paid)
        // This ensures that if a user has a long-term Regular plan but starts a Premium trial,
        // the system correctly identifies them as Premium.
        $activeSubs = $this->subscriptions()
            ->where('expires_at', '>', now())
            ->whereIn('status', ['active', 'trial'])
            ->with('plan')
            ->get();

        if ($activeSubs->isEmpty()) {
            return null;
        }

        // If ANY active subscription is Premium, the user is Premium.
        return $activeSubs->contains(fn ($s) => $s->plan?->tier === 'premium')
            ? 'premium'
            : 'regular';
    }

    public function getHasUsedRegularAttribute(): bool
    {
        return $this->subscriptions()
            ->whereHas('plan', fn ($q) => $q->where('tier', 'regular'))
            ->whereIn('status', ['trial', 'expired', 'cancelled'])
            ->exists();
    }

    /**
     * Check if the user has ever used a premium/VIP trial.
     */
    public function getHasUsedPremiumAttribute(): bool
    {
        return $this->subscriptions()
            ->whereHas('plan', fn ($q) => $q->where('tier', 'premium'))
            ->whereIn('status', ['trial', 'expired', 'cancelled'])
            ->exists();
    }

    /**
     * Relationship: One User can have multiple Crypto Addresses (BTC, TRC20, etc.)
     */
    public function cryptoAddresses()
    {
        return $this->hasMany(\App\Models\CryptoAddress::class);
    }

    /**
     * Helper to get the user's primary TRON address for USDT deposits.
     */
    public function tronAddress()
    {
        return $this->cryptoAddresses()->where('blockchain', 'TRON')->first();
    }
}
