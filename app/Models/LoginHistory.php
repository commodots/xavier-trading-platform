<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'device',
        'browser',
        'platform',
        'location',
        'successful',
        'logged_in_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'logged_in_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a login attempt
     */
    public static function record(
        int $userId,
        string $ipAddress,
        ?string $device,
        string $browser,
        string $platform,
        ?string $location,
        bool $successful
    ): self {
        return static::create([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'device' => $device,
            'browser' => $browser,
            'platform' => $platform,
            'location' => $location,
            'successful' => $successful,
            'logged_in_at' => now(),
        ]);
    }
}