<?php

namespace Database\Seeders;

use App\Models\AdvisoryPost;
use App\Models\ModelPortfolio;
use Illuminate\Database\Seeder;

class AdvisorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $copyTrading = ModelPortfolio::create([
            'name' => 'Xavier Copy Trading',
            'description' => 'A high-conviction blend of US Tech Equities and blue-chip Crypto assets designed for long-term capital appreciation.',
            'risk_profile' => 'aggressive',
            'is_premium' => true,
            'starting_value' => 100000.00,
        ]);
        ModelPortfolio::create([
            'name' => 'Xavier Conservative',
            'description' => 'Focused on wealth preservation using low-volatility global stocks and yield-bearing stablecoin protocols.',
            'risk_profile' => 'conservative',
            'is_premium' => false,
            'starting_value' => 50000.00,
        ]);
        // 2. Create Advisory Posts
        // Regular Access
        AdvisoryPost::create([
            'title' => 'Navigating the Q1 2026 Tech Correction',
            'content' => 'As interest rates stabilize, we are seeing a rotation from high-growth SaaS into value-driven fintech. For retail investors, this is a prime time to look at companies with strong cash flow. Keep an eye on the $USD/NGN peg as it impacts local purchasing power for global stocks.',
            'market_type' => 'international',
            'risk_level' => 'medium',
            'is_premium' => false,
        ]);
        // Premium Access
        AdvisoryPost::create([
            'title' => 'Alpha Alert: The Layer 2 Scaling Thesis',
            'content' => 'Our proprietary sentiment analysis indicates a massive liquidity shift toward Ethereum Layer 2 solutions. We’ve identified three undervalued tokens currently trading at a 15% discount to their 30-day moving average. Premium members can view the specific entry points in the AI Picks section.',
            'market_type' => 'crypto',
            'risk_level' => 'high',
            'is_premium' => true,
        ]);
        AdvisoryPost::create([
            'title' => 'Daily AI Insight: NVIDIA ($NVDA)',
            'content' => 'Sentiment remains bullish. Despite the recent rally, our models suggest institutional accumulation is still ongoing at the $900 support level.',
            'market_type' => 'international',
            'risk_level' => 'medium',
            'is_premium' => true,
        ]);
    }
}
