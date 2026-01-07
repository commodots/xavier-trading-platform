<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;
use App\Models\NewTransaction;
use App\Models\Portfolio;

class OmsController extends Controller
{
    public function placeOrder(Request $request)
    {
        $request->validate([
            "market" => "required|in:NGX,GLOBAL,CRYPTO",
            "symbol" => "required",
            "company" => "required",
            "market_price" => "required|numeric",
            "amount" => "required|numeric|min:1",
            "side" => "required|in:buy,sell"
        ]);

        $user = Auth::user();
        $USD_RATE = 1500;
        $currency = "NGN";

        if ($request->market === "NGX") {
            $units = $request->amount / $request->market_price;
        } else {
            $amountInUsd = $request->amount / $USD_RATE;
            $units = $amountInUsd / $request->market_price;
        }

        // --- PRE-CHECK BEFORE TRANSACTION ---
        if ($request->side === 'buy') {
            $wallet = Wallet::where('user_id', $user->id)->where('currency', $currency)->first();
            if (!$wallet || $wallet->balance < $request->amount) {
                return response()->json(["success" => false, "message" => "Insufficient wallet balance"], 400);
            }
        } else {
            $holding = Portfolio::where('user_id', $user->id)->where('symbol', $request->symbol)->first();
            // Check if user owns enough units to sell
            if (!$holding || $holding->quantity < $units) {
                return response()->json(["success" => false, "message" => "Insufficient holdings to sell"], 400);
            }
        }


        return DB::transaction(function () use ($request, $user, $currency, $units) {
            if ($request->side === 'buy') {
                // Deduct cash immediately
                $wallet = Wallet::where('user_id', $user->id)->where('currency', $currency)->lockForUpdate()->first();
                $wallet->decrement('balance', $request->amount);

                NewTransaction::create([
                    'user_id' => $user->id,
                    'type' => $request->market === 'CRYPTO' ? 'buy_crypto' : 'buy_stock',
                    'amount' => $request->amount,
                    'currency' => $currency,
                    'status' => 'completed',
                    'meta' => ['symbol' => $request->symbol, 'info' => 'Trade Placement']
                ]);
            } else {
                // Deduct units immediately so they aren't "double-sold"
                $holding = Portfolio::where('user_id', $user->id)->where('symbol', $request->symbol)->lockForUpdate()->first();
                $holding->decrement('quantity', $units);

                NewTransaction::create([
                    'user_id' => $user->id,
                    'type' => $request->market === 'CRYPTO' ? 'sell_crypto' : 'sell_stock',
                    'amount' => $request->amount,
                    'currency' => $currency,
                    'status' => 'pending',
                    'meta' => ['symbol' => $request->symbol, 'info' => 'Sell Order Placement']
                ]);
            }
            $order = Order::create([
                "user_id" => $user->id,
                "market" => $request->market,
                "symbol" => $request->symbol,
                "company" => $request->company,
                "market_price" => $request->market_price,
                "amount" => $request->amount,
                "quantity" => $units,
                "units"  => $units,
                "side" => $request->side,
                "type"  => 'market',
                "price" => $request->market_price,
                "currency" => $currency,
                "status" => "open"
            ]);

            ActivityLog::log($user->id, 'ORDER_PLACED', [
                'order_id' => $order->id,
                'symbol' => $order->symbol,
                'side' => $request->side
            ]);

            return response()->json([
                "success" => true,
                "message" => "Order placed",
                "data" => $order
            ]);
        });
    }

    public function listOrders()
    {
        return response()->json([
            "success" => true,
            "data" => Order::where('user_id', Auth::id())->orderByDesc('id')->get()
        ]);
    }

    public function cancelOrder($id)
    {
        $user = Auth::user();

        return DB::transaction(function () use ($id, $user) {

            $order = Order::where('id', $id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return response()->json(["success" => false, "message" => "Order not found"], 404);
            }

            // Check if the status allows cancellation
            $cancellableStatuses = ['open', 'pending_market', 'partially_filled'];
            if (!in_array($order->status, $cancellableStatuses)) {
                return response()->json([
                    "success" => false,
                    "message" => "This order is already {$order->status} and cannot be canceled."
                ], 400);
            }

            $filledUnits = $order->filled_quantity ?? 0;
            $remainingUnits = $order->units - $filledUnits;

            $remainingRatio = $order->units > 0 ? ($remainingUnits / $order->units) : 0;
            $refundCashAmount = $order->amount * $remainingRatio;

            // Refund only the unused portion
            if ($order->side === 'buy') {
                // REFUND CASH to Wallet
                $wallet = Wallet::where('user_id', $user->id)
                    ->where('currency', $order->currency)
                    ->lockForUpdate()
                    ->first();

                if ($refundCashAmount > 0) {
                    $wallet->increment('balance', $refundCashAmount);
                }
                $message = "Order canceled. Refunded " . $order->currency . " " . number_format($refundCashAmount, 2);
            } else {
                // REFUND UNITS to Portfolio
                $holding = Portfolio::where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->first();

                if ($remainingUnits > 0) {
                    // If the holding record was deleted somehow, re-create it; 
                    // otherwise, just increment back the locked units.
                    if ($holding) {
                        $holding->increment('quantity', $remainingUnits);
                    } else {
                        Portfolio::create([
                            'user_id' => $user->id,
                            'symbol' => $order->symbol,
                            'quantity' => $remainingUnits,
                            'name' => $order->company,
                            'category' => $order->market,
                            'currency' => $order->currency,
                            'avg_price' => $order->market_price,
                            'market_price' => $order->market_price
                        ]);
                    }
                }
                $message = "Order canceled. Returned " . number_format($remainingUnits, 6) . " " . $order->symbol . " to portfolio.";
            }

            $order->update(['status' => 'canceled']);

            ActivityLog::log($user->id, 'ORDER_CANCELED', [
                'order_id' => $order->id,
                'symbol' => $order->symbol,
                'refund_type' => $order->side === 'buy' ? 'cash' : 'units'
            ]);

            return response()->json([
                "success" => true,
                "message" => $message, 
                "data" => $order
            ]);
        });
    }
}
