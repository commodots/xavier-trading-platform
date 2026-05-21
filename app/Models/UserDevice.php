<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = ['user_id', 'device_name', 'ip_address', 'last_active_at', 'is_trusted'];

    protected $casts = ['last_active_at' => 'datetime', 'is_trusted' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
