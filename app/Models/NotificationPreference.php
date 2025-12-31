<?php

namespace app\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'sms',
        'push',
        'monthly_statements',
        'newsletters'
    ];


    protected $casts = [
        'email' => 'boolean',
        'sms' => 'boolean',
        'push' => 'boolean',
        'monthly_statements' => 'boolean',
        'newsletters' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
