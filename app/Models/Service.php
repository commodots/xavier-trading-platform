<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ServiceConnection;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'is_active'];

    public function connections()
    {
        return $this->hasMany(ServiceConnection::class);
    }

    public function activeConnection()
    {
        return $this->connections()->where('is_active', true)->first(); 
    }
}
