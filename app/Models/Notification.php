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
        'data',
        'notifiable_type',
        'notifiable_id',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function notifiable()
    {
        return $this->morphTo();
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
           
            $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
            if (is_array($data) && !empty($data)) {
                // Only fill from data if the column values weren't already provided
                if (empty($notification->title)) $notification->title = $data['title'] ?? null;
                if (empty($notification->message)) $notification->message = $data['message'] ?? null;
                if (empty($notification->action)) $notification->action = $data['action'] ?? null;
                if (empty($notification->icon)) $notification->icon = $data['icon'] ?? null;
            }
            
            // Ensure data is a JSON string
            if (!is_string($notification->data)) {
                $notification->data = json_encode($notification->data ?? []);
            }
        });
    }
}
