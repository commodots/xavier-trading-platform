<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;


class DemoLedger extends Model
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
