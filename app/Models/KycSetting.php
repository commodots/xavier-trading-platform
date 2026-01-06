<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycSetting extends Model
{
    use HasFactory;

    protected $table = 'kyc_settings';

    protected $fillable = [
        'tier',
        'tier_name',
        'daily_limit',
        'required_documents'
    ];

    protected $casts = [
        'daily_limit' => 'decimal:2',
        'required_documents' => 'array'
    ];

    protected $attributes = [
        'required_documents' => '[]'
    ];
    public static function getByTier(int $tier)
    {
        return self::where('tier', $tier)->first();
    }
}
