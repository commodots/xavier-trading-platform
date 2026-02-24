<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockPrediction extends Model
{
    protected $fillable = [
        'symbol', 
        'prediction_date', 
        'predicted_price', 
        'confidence_score', 
        'model_version'
    ];

    protected $casts = [
        'prediction_date' => 'date',
    ];
}