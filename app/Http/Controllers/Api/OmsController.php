<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Trade;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use App\Models\NewTransaction;
use App\Models\Portfolio;
use Carbon\Carbon;

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
        $currency = $request->market === "NGX" ? "NGN" : "USD";
        $units = floor($request->amount / $request->market_price);
        $actualCost = $units * $request->market_price;

        return DB::transaction(function () use ($request, $user, $currency, $units, $actualCost) {
            
            $wallet = $user->fxWallet($currency); 
            $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $unclearedCol = $currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';

            $holding = Portfolio::firstOrCreate(
                ['user_id' => $user->id, 'symbol' => $request->symbol],
                [
                    'name' => $request->company,
                    'category' => $request->market === 'NGX' ? 'local' : strtolower($request->market),
                    'currency' => $currency,
                    'market_price' => $request->market_price,
                    'quantity' => 0,
                    'avg_price' => 0,
                    'cleared_quantity' => 0,
                    'uncleared_quantity' => 0
                ]
            );

            // --- T+0: PRE-CHECK AND LOCK FUNDS/ASSETS ---
            if ($request->side === 'buy') {
                if ($wallet->$clearedCol < $actualCost) {
                    return response()->json(["success" => false, "message" => "Insufficient {$currency} cleared balance"], 400);
                }

                // Lock Cash (Move from cleared to LOCKED - Outgoing money)
                $wallet->decrement($clearedCol, $actualCost);
                $wallet->increment('locked', $actualCost);

                //  Safely Update Portfolio (Quantity + Avg Price)
                $currentQty = $holding->quantity;
                $currentAvgPrice = $holding->avg_price;
                $newTotalQty = $currentQty + $units;
                $newAvgPrice = (($currentQty * $currentAvgPrice) + $actualCost) / $newTotalQty;

                $holding->update([
                    'quantity' => $newTotalQty,
                    'uncleared_quantity' => $holding->uncleared_quantity + $units,
                    'avg_price' => $newAvgPrice
                ]);

            } else { 
                // SELL LOGIC
                if ($holding->cleared_quantity < $units) {
                    return response()->json(["success" => false, "message" => "Insufficient cleared holdings to sell"], 400);
                }
                
                // Lock Shares (Move from cleared to uncleared pending sale)
                $holding->decrement('cleared_quantity', $units);
                $holding->increment('uncleared_quantity', $units);

                //Add incoming cash as UNCLEARED (Incoming money)
                $wallet->increment($unclearedCol, $actualCost);
            }

            // Create Order
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
                "status" => "open", 
                "filled_quantity" => $units
            ]);

            // Create trade record WITH SETTLEMENT STATUS AND DATE
            Trade::create([
                'order_id' => $order->id,
                'price' => $request->market_price,
                'quantity' => $units,
                'fee' => 0,
                'settlement_status' => 'pending',
                'settlement_date' => Carbon::now()->addWeekdays(2)->toDateString(),
            ]);

            $transactionType = $request->market === 'CRYPTO'
                ? ($request->side === 'buy' ? 'buy_crypto' : 'sell_crypto')
                : ($request->side === 'buy' ? 'buy_stock' : 'sell_stock');

            NewTransaction::create([
                'user_id' => $user->id,
                'type' => $transactionType,
                'amount' => $actualCost,
                'currency' => $currency,
                'status' => 'pending', 
                'meta' => ['symbol' => $request->symbol, 'info' => 'T+2 Trade Settlement']
            ]);

            ActivityLog::log($user->id, 'ORDER_PLACED', [
                'order_id' => $order->id,
                'symbol' => $order->symbol,
                'side' => $request->side
            ]);

            return response()->json([
                "success" => true,
                "message" => "Order placed - settlement pending",
                "data" => $order
            ]);
        });
    }

    public function listOrders()
    {
        return response()->json([
            "success" => true,
            "data" => Order::where('user_id', Auth::id())
                ->orderByDesc('id')
                ->get()
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

            $cancellableStatuses = ['open', 'pending_market', 'partially_filled'];
            if (!in_array($order->status, $cancellableStatuses)) {
                return response()->json(["success" => false, "message" => "This order is already {$order->status}."], 400);
            }

            $filledUnits = $order->filled_quantity ?? 0;
            $remainingUnits = $order->units - $filledUnits;
            $remainingRatio = $order->units > 0 ? ($remainingUnits / $order->units) : 0;
            $refundCashAmount = $order->amount * $remainingRatio;

            $wallet = $user->fxWallet($order->currency);
            $clearedCol = $order->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $unclearedCol = $order->currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';
            
            $holding = Portfolio::where('user_id', $user->id)->where('symbol', $order->symbol)->lockForUpdate()->first();

            if ($order->side === 'buy') {
                // Unlock Funds (Move from LOCKED back to CLEARED)
                if ($refundCashAmount > 0) {
                    $wallet->decrement('locked', $refundCashAmount);
                    $wallet->increment($clearedCol, $refundCashAmount);
                }
                // Discard incoming unfulfilled shares
                if ($holding && $remainingUnits > 0) {
                    $holding->decrement('quantity', $remainingUnits);
                    $holding->decrement('uncleared_quantity', $remainingUnits);
                }
                $message = "Order canceled. Refunded " . $order->currency . " " . number_format($refundCashAmount, 2);
            } else {
                // Unlock Shares
                if ($remainingUnits > 0 && $holding) {
                    $holding->decrement('uncleared_quantity', $remainingUnits);
                    $holding->increment('cleared_quantity', $remainingUnits);
                }
                // Discard incoming unfulfilled cash
                if ($refundCashAmount > 0) {
                    $wallet->decrement($unclearedCol, $refundCashAmount);
                }
                $message = "Order canceled. Returned " . number_format($remainingUnits, 6) . " " . $order->symbol . " to portfolio.";
            }

            $order->update(['status' => 'canceled']);
            $order->trades()->where('settlement_status', 'pending')->update(['settlement_status' => 'canceled']);

            return response()->json(["success" => true, "message" => $message, "data" => $order]);
        });
    }
}