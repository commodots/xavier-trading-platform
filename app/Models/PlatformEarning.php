<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformEarning extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *  transaction_id: Links to the specific transaction row
     * amount: The actual fee amount collected (e.g., 100.00)
     * source: Useful for filtering (e.g., 'transaction_fee', 'subscription', 'withdrawal_penalty')
     */
    protected $fillable = [
        'transaction_id',
        'amount',
        'source'
    ];

    /**
     * Relationship: Every earning belongs to a transaction.
     * This allows you to see WHO generated the earning (via $earning->transaction->user).
     */
    public function transaction()
    {
        return $this->belongsTo(NewTransaction::class, 'transaction_id');
    }
}