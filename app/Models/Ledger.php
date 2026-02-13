<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    protected $table = 'ledgers';

    protected $fillable = [
        'user_id',
        'currency',
        'amount',
        'type',
        'status',
        'reference',
        'meta',
        'is_platform',
    ];

    protected $casts = [
        'amount' => 'float',
        'meta' => 'array',
        'is_platform' => 'boolean',
    ];

    /**
     * Get user for this entry
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
