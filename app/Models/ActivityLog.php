<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'activity',
        'description',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an activity
     */
    public static function log($userId, string $activity, array $properties = []): self
    {
        return static::create([
            'user_id' => $userId,
            'activity' => $activity,
            'properties' => $properties,
        ]);
    }
}