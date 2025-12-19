<?php

namespace app\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
         'email',
          'sms',
           'push'
    ];

  
    protected $casts = [
        'email' => 'boolean',
        'sms' => 'boolean',
        'push' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}