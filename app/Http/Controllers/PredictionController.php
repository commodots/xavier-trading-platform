<?php

namespace App\Http\Controllers;

use App\Models\StockPrediction;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    /**
     * Get the highest confidence stock picks from the AI engine
     */
    public function topPicks()
    {
        // Fetch predictions with > 75% confidence
        $picks = StockPrediction::where('confidence_score', '>', 0.75)
            ->orderBy('confidence_score', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($pick) {
                return [
                    'symbol' => $pick->symbol,
                    // Convert 0.85 to 85 for the frontend UI
                    'confidence' => round($pick->confidence_score * 100), 
                    'predicted_price' => $pick->predicted_price
                ];
            });

        return response()->json(['success' => true, 'data' => $picks]);
    }
}