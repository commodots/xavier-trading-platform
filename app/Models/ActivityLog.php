<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'activity', 'ip_address', 'user_agent', 'details'];

    protected $casts = [
        'details' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log($userId, $activity, $details = null)
{
    return self::create([
        'user_id' => $userId,
        'activity' => $activity,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'details' => $details,
    ]);
}
}
