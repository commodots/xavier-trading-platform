<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'action',
        'icon',
        'read_at',
        'action_url',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsUnread()
    {
        $this->update(['read_at' => null]);
    }

    protected static function booted()
    {
        static::creating(function ($notification) {
            if (empty($notification->data)) {
                $notification->data = json_encode([]);
            }

            $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
            if (is_array($data)) {
                $notification->message = $notification->message ?? ($data['message'] ?? 'Notification received');
                $notification->title = $notification->title ?? ($data['title'] ?? 'System Alert');
                $notification->action = $notification->action ?? ($data['action'] ?? 'View Details');
                $notification->icon = $notification->icon ?? ($data['icon'] ?? '🔔');
            }
        });
    }
}
