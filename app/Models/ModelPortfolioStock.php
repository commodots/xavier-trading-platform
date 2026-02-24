<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelPortfolioStock extends Model
{
    protected $fillable = [
        'model_portfolio_id', 
        'symbol', 
        'allocation_percentage'
    ];

    public function portfolio()
    {
        return $this->belongsTo(ModelPortfolio::class, 'model_portfolio_id');
    }
}