<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'device',
        'browser',
        'platform',
        'last_activity',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update or create session
     */
    public static function updateOrCreateSession(
        int $userId,
        string $sessionId,
        string $ipAddress,
        ?string $device,
        ?string $browser,
        ?string $platform
    ): self {
        return static::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'device' => $device,
                'browser' => $browser,
                'platform' => $platform,
                'last_activity' => now(),
            ]
        );
    }

    /**
     * Get active sessions for user
     */
    public static function getActiveSessions(int $userId)
    {
        return static::where('user_id', $userId)
            ->orderBy('last_activity', 'desc')
            ->get();
    }
}