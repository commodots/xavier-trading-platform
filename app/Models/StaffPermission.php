<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffPermission extends Model
{
    use HasFactory;

    protected $table = 'staff_permissions';

    protected $fillable = ['role', 'permissions'];

    protected $casts = [
        'permissions' => 'array'
    ];

    public static function forRole(string $role)
    {
        return self::where('role', $role)->first();
    }
}
