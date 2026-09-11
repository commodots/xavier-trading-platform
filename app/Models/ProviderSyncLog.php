<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderSyncLog extends Model
{
    protected $fillable = [
        'provider',
        'operation',
        'status',
        'reference',
        'error_message',
        'request',
        'response',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
