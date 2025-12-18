<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceConnection extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'service_id', 
        'mode', 
        'base_url', 
        'headers', 
        'parameters', 
        'credentials', 
        'is_active' 
    ];

    protected $casts = [
        'headers' => 'array', 
        'parameters' => 'array', 
        'credentials' => 'array', 
    ];
}
