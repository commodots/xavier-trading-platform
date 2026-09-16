<?php

namespace App\Models\Demo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoTransaction extends Model
{
    protected $table = 'demo_transactions';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'charge',
        'net_amount',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
