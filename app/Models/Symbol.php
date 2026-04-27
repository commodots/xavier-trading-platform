<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    protected $table = 'symbols';

    protected $fillable = [
        'symbol',
        'name',
        'type',
        'exchange',
    ];
}
