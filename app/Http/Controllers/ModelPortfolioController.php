<?php

namespace App\Http\Controllers;

use App\Models\ModelPortfolio;
use App\Models\Order;
use App\Models\Portfolio;
use App\Services\PriceService;
use App\Services\Execution\NgxDummyAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

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

                $wallet = $user->fxWallet('NGN');

                //Lock the funds (moves from ngn_cleared to locked)
                $wallet->reserve($intendedAmount);

                $tradesExecuted = 0;

                foreach ($portfolio->stocks as $stock) {
                    $allocation = ($intendedAmount * $stock->allocation_percentage) / 100;
                    $price = app(PriceService::class)->getCurrentPrice($stock->symbol, 'local');
                    $quantity = floor($allocation / $price);
                    $actualCost = $quantity * $price;

                    if ($quantity > 0) {
                        //Create the Order
                        $order = Order::create([
                            'user_id'      => $user->id,
                            'symbol'       => $stock->symbol,
                            'side'         => 'buy',
                            'type'         => 'market',
                            'quantity'     => $quantity,
                            'units'        => $quantity,
                            'price'        => $price,
                            'market_price' => $price,
                            'status'       => 'open',
                            'amount'       => $actualCost,
                            'currency'     => 'NGN',
                            'market_type'  => 'local'
                        ]);

                        //Send to Execution Engine
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
                                'market_price' => $price
                            ]
                        );

                        // Instantly increase their total and uncleared quantities
                        $portfolioRecord->increment('quantity', $quantity);
                        $portfolioRecord->increment('uncleared_quantity', $quantity);

                        // Update the average purchase price
                        $portfolioRecord->update(['avg_price' => $price]);

                        $tradesExecuted++;
                    }
                }

                if ($tradesExecuted === 0) {
                    throw new Exception("Amount is too low to purchase any full shares.");
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Portfolio copied successfully!'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
