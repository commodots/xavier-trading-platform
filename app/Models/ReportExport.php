<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Iluminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class ReportExport extends Model
{
    protected $fillable = [
        'user_id',
        'report_name',
        'export_type',
        'file_name',
        'disk',
        'path',
        'status',
        'generated_at',
        'expires_at',
        'downloaded_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}