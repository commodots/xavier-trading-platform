<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watchlist extends Model
{
    protected $fillable = [
        'user_id', 'symbol', 'name', 'market', 'currency', 'added_price'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
