<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioPerformanceLog extends Model
{
    protected $fillable = [
        'model_portfolio_id', 
        'value', 
        'return_percentage'
    ];

    public function portfolio()
    {
        return $this->belongsTo(ModelPortfolio::class, 'model_portfolio_id');
    }
}