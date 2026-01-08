<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceConfig extends Model
{
    //
    protected $fillable = [
        'service',
        'type',
        'mode',
        'base_url',
        'headers',
        'params',
        'credentials',
        'is_active',
    ];

    protected $casts = [
        'headers' => 'array',
        'params' => 'array',
        'credentials' => 'array',
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
