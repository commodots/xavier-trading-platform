<?php

namespace App\Http\Controllers;

use App\Models\ModelPortfolio;
use App\Models\Order;
use App\Models\Portfolio;
use App\Models\Wallet;
use App\Services\Execution\NgxDummyAdapter;
use App\Services\PriceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModelPortfolioController extends Controller
{
    public function index()
    {
        $portfolios = ModelPortfolio::with('stocks')->get();

        return response()->json(['success' => true, 'data' => $portfolios]);
    }

    public function copyPortfolio($id, Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:5000']);

        $portfolio = ModelPortfolio::with('stocks')->findOrFail($id);
        $intendedAmount = $request->amount;
        $user = $request->user();

        try {
            DB::transaction(function () use ($portfolio, $intendedAmount, $user) {

                // Lock the wallet row to prevent race conditions (double spending)
                // We first ensure the wallet exists via fxWallet, then lock it.
                $walletId = $user->fxWallet('NGN')->id;
                $wallet = Wallet::where('id', $walletId)->lockForUpdate()->first();

                // Lock the funds (moves from ngn_cleared to locked)
                $wallet->reserve($intendedAmount);

                $tradesExecuted = 0;

                foreach ($portfolio->stocks as $stock) {
                    $allocation = ($intendedAmount * $stock->allocation_percentage) / 100;
                    $price = app(PriceService::class)->getCurrentPrice($stock->symbol, 'local');
                    $quantity = floor($allocation / $price);
                    $actualCost = $quantity * $price;

                    if ($quantity > 0) {
                        // Create the Order
                        $order = Order::create([
                            'user_id' => $user->id,
                            'symbol' => $stock->symbol,
                            'side' => 'buy',
                            'type' => 'market',
                            'quantity' => $quantity,
                            'units' => $quantity,
                            'price' => $price,
                            'market_price' => $price,
                            'status' => 'open',
                            'amount' => $actualCost,
                            'currency' => 'NGN',
                            'market_type' => 'local',
                        ]);

                        // Send to Execution Engine
                        $execution = app(NgxDummyAdapter::class)->send($order);

                        if ($execution['status'] !== 'accepted') {
                            throw new Exception("NGX Adapter rejected the order for {$stock->symbol}");
                        }

                        // Add the shares to the Portfolio as "Uncleared" so the user sees them.
                        $portfolioRecord = Portfolio::firstOrCreate(
                            ['user_id' => $user->id, 'symbol' => $stock->symbol],
                            [
                                'name' => $stock->symbol,
                                'quantity' => 0,
                                'cleared_quantity' => 0,
                                'uncleared_quantity' => 0,
                                'category' => 'local',
                                'currency' => 'NGN',
                                'avg_price' => $price,
                                'market_price' => $price,
                            ]
                        );

                        // Recalculate average price before updating quantities
                        $newTotalQuantity = $portfolioRecord->quantity + $quantity;
                        $newAvgPrice = (($portfolioRecord->quantity * $portfolioRecord->avg_price) + $actualCost) / ($newTotalQuantity > 0 ? $newTotalQuantity : 1);

                        // Instantly increase their total and uncleared quantities
                        $portfolioRecord->increment('quantity', $quantity);
                        $portfolioRecord->increment('uncleared_quantity', $quantity);

                        // Update the average purchase price
                        $portfolioRecord->update(['avg_price' => $newAvgPrice, 'market_price' => $price]);

                        $tradesExecuted++;
                    }
                }

                if ($tradesExecuted === 0) {
                    throw new Exception('Amount is too low to purchase any full shares.');
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Portfolio copied successfully!',
            ]);
        } catch (Exception $e) {
            Log::error('Copy portfolio failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to copy portfolio at this time.',
            ], 500);
        }
    }
}
