<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class ReportTemplate extends Model
{
    protected $fillable = [
        'name',
        'category',
        'filters',
        'created_by',
        'is_public',
    ];

    protected $casts = [
        'filters' => 'json',
        'is_public' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}