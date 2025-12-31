<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewTransaction extends Model
{
    protected $table = 'new_transactions_table';

    protected $fillable = [
        'user_id', 'type', 'amount', 'currency', 
        'charge', 'net_amount', 'status', 'meta'
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