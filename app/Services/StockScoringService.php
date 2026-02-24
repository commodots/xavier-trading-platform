<?php

namespace App\Services;

class StockScoringService
{
    /**
     * Calculates a stock score out of 100 based on basic financial metrics.
     */
    public function score(array $stockData)
    {
        $score = 0;

        if (isset($stockData['pe_ratio']) && $stockData['pe_ratio'] < 15) $score += 20;
        if (isset($stockData['revenue_growth']) && $stockData['revenue_growth'] > 10) $score += 25;
        if (isset($stockData['debt_ratio']) && $stockData['debt_ratio'] < 0.5) $score += 15;
        if (isset($stockData['momentum']) && $stockData['momentum'] > 5) $score += 20;
        if (isset($stockData['volume_spike']) && $stockData['volume_spike']) $score += 20;

        // Ensure the score never accidentally goes above 100
        return min($score, 100);
    }

    /**
     * Converts the numerical score into a human-readable rating.
     */
    public function rating($score)
    {
        if ($score > 80) return "Strong Buy";
        if ($score > 60) return "Buy";
        if ($score > 40) return "Hold";
        return "Watch";
    }
}