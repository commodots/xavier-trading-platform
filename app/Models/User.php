<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, Notifiable, MustVerifyEmail;
    use HasRoles;

    protected $_trialSubscription;
    protected $_currentTier;

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
        'kyc_locked',
        'kyc_note',
        'password',
        'country',
        'next_of_kin',
        'next_of_kin_phone',
        'next_of_kin_email',
        'bank_name',
        'bvn',
        'kyc_status',
        'trading_mode',
        'email_verified_at',
        'subscription_status', 'trial_ends_at', 'last_active_at',
        'wallet_balance', 'wallet_debt', 'last_fee_charged_at', 'next_fee_due_at',
        'google2fa_secret',
        'google2fa_enabled',
        'two_factor_recovery_codes',
        'is_suspended',
        'suspension_reason',
        'next_billing_date',
    ];

    protected $guard_name = 'api'; // For sanctum API guards

    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
        'bvn',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dob' => 'date',
        'bvn' => 'encrypted',
        'trial_ends_at' => 'datetime',
        'last_active_at' => 'datetime',
        'last_fee_charged_at' => 'datetime',
        'next_fee_due_at' => 'datetime',
        'wallet_balance' => 'decimal:2',
        'wallet_debt' => 'decimal:2',
        'google2fa_enabled' => 'boolean',
        'two_factor_recovery_codes' => 'encrypted',
        'is_suspended' => 'boolean',
        'next_billing_date' => 'datetime',
    ];

    protected $appends = [
        'avatar',
        'kyc_verified',
        'verification_level',
    ];

    // hasMany — named wallets() to match Laravel convention
    public function wallets()
    {
        return $this->hasMany(\App\Models\Wallet::class);
    }

    /** @deprecated Use wallets() */
    public function wallet()
    {
        return $this->wallets();
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
        return $this->kyc()->whereIn('status', \App\Services\KycService::VERIFIED_STATUSES)->exists();
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
            get: function ($value) {
                if (!$value) return null;
                
                // Decrypt the raw database string payload
                $decrypted = Crypt::decryptString($value);
                
                // If it's our packed JSON string, decode it and return just the secret string
                $data = json_decode($decrypted, true);
                if (is_array($data) && isset($data['secret'])) {
                    return $data['secret'];
                }
                
                return $decrypted;
            },
            set: fn ($value) => $value === null ? null : Crypt::encryptString($value),
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
            set: fn ($value) => $value,
        );
    }

    /**
     * Check if the user's profile image is locked (cannot be changed after KYC)
     */
    public function isProfileImageLocked(): bool
    {
        return (bool) ($this->kyc_locked ?? false);
    }

    public function linkedAccounts()
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }
    public function notifications()
    {
      
        return $this->morphMany(\App\Models\Notification::class, 'notifiable')->latest();
    }

    public function holdings()
    {
        return $this->hasMany(Portfolio::class);
    }

    public function activities()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function billingRecords() 
    { 
        return $this->hasMany(BillingRecord::class); 
    }
    public function fees() 
    { 
        return $this->hasMany(Fee::class); 
    }
    public function watchlists()
    { 
        return $this->hasMany(Watchlist::class);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function riskFlags()
    {
        return $this->hasMany(RiskFlag::class);
    }

    /**
     * Check if the user is suspended
     */
    public function isSuspended(): bool
    {
        return (bool) $this->is_suspended;
    }

    /**
     * Check if the user has an active (non-expired) subscription
     */
    public function isPaying(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Check if the user is on trial
     */
    public function isTrial(): bool
    {
        return $this->onTrial();
    }

    /**
     * active users (not suspended, email verified)
     */
    public function scopeActive($query)
    {
        return $query->where('is_suspended', false)
            ->whereNotNull('email_verified_at');
    }

    /**
     * Scope: trial users
     */
    public function scopeTrial($query)
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->where('status', 'trial')
              ->where('expires_at', '>', now());
        });
    }

    /**
     * paying users
     */
    public function scopePaying($query)
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->where('status', 'active')
              ->where('expires_at', '>', now());
        });
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
        return $this->wallets()->firstOrCreate(
            ['currency' => $currency],
            ['ngn_cleared' => 0, 'ngn_uncleared' => 0, 'usd_cleared' => 0, 'usd_uncleared' => 0]
        );
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
        return (int) ($settings?->trial_days ?? 7);
    }

    public function getTrialExpiresAtAttribute()
    {
        return $this->trialSubscription()?->expires_at?->toIso8601String();
    }

    public function getCurrentTierAttribute(): ?string
    {
        if (!isset($this->_currentTier)) {
            $activeSubs = $this->subscriptions()
                ->where('expires_at', '>', now())
                ->whereIn('status', ['active', 'trial'])
                ->with('plan')
                ->get();

            $this->_currentTier = $activeSubs->isEmpty()
                ? null
                : ($activeSubs->contains(fn ($s) => $s->plan?->tier === 'premium') ? 'premium' : 'regular');
        }

        return $this->_currentTier;
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

    public function getTierAttribute(): string
    {
        return $this->current_tier ?? 'free';
    }

    public function isPremium(): bool
    {
        return $this->getCurrentTierAttribute() === 'premium';
    }

    public function getVerificationLevelAttribute(): string
    {
        return $this->kyc?->level ?? 'none';
    }

    public function getKycTierAttribute(): int
    {
        return $this->kyc?->tier ?? 0;
    }
}