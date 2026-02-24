<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelPortfolio extends Model
{
    protected $fillable = [
        'name', 
        'description', 
        'risk_profile', 
        'starting_value', 
        'is_premium'
    ];

    protected $casts = [
        'is_premium' => 'boolean',
    ];

    public function stocks()
    {
        return $this->hasMany(ModelPortfolioStock::class);
    }

    public function performanceLogs()
    {
        return $this->hasMany(PortfolioPerformanceLog::class);
    }
}