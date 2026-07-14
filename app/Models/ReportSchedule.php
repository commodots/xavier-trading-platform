<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    protected $fillable = [
        'report_name',
        'frequency',
        'filters',
        'email_to',
        'last_run',
        'next_run',
        'enabled',
    ];

    protected $casts = [
        'filters' => 'array',
        'email_to' => 'array',
        'last_run' => 'datetime',
        'next_run' => 'datetime',
        'enabled' => 'boolean',
    ];
}