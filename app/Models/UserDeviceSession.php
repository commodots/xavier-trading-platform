<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDeviceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_name',
        'device_type',
        'browser',
        'os',
        'ip_address',
        'last_used_at',
        'is_trusted',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_trusted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('last_used_at', '>', now()->subDays(30));
    }

    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    public function markAsUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    public function trust()
    {
        $this->update(['is_trusted' => true]);
    }

    public function revoke()
    {
        $this->delete();
    }
}
