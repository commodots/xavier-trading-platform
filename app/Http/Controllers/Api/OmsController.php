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
use App\Services\Execution\NgxDummyAdapter;
use App\Services\SettlementService;

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
        $currency = "NGN"; // Assuming NGN is default unless trading GLOBAL

        if ($request->market === "NGX") {
            $units = floor($request->amount / $request->market_price);
        } else {
            // If trading Global, currency should be USD. Adjusting logic based on your setup.
            $currency = "USD"; 
            $units = floor($request->amount / $request->market_price);
        }

        $actualCost = $units * $request->market_price;

        return DB::transaction(function () use ($request, $user, $currency, $units, $actualCost) {
            
            // --- PRE-CHECK AND DEDUCT FUNDS/ASSETS ---
            if ($request->side === 'buy') {
                $wallet = $user->fxWallet($currency);
                $clearedBalance = $currency === 'NGN' ? $wallet->ngn_cleared : $wallet->usd_cleared;
                
                if ($clearedBalance < $actualCost) {
                    return response()->json(["success" => false, "message" => "Insufficient {$currency} cleared balance"], 400);
                }

                // Actually deduct the money from cleared funds
                $wallet->debit($actualCost, 'cleared');

            } else {
                $holding = Portfolio::where('user_id', $user->id)->where('symbol', $request->symbol)->first();
                if (!$holding || $holding->cleared_quantity < $units) {
                    return response()->json(["success" => false, "message" => "Insufficient cleared holdings to sell"], 400);
                }
                
                // Deduct units from cleared_quantity immediately
                $holding->decrement('cleared_quantity', $units);
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

            // Create trade record for settlement
            $trade = Trade::create([
                'order_id' => $order->id,
                'price' => $request->market_price,
                'quantity' => $units,
                'fee' => 0,
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
                "message" => "Order placed - settlement pending T+2",
                "data" => $order
            ]);
        });
    }

    /**
     * RESTORED: Fetch user's order history
     */
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

            if ($order->side === 'buy') {
                $wallet = $user->fxWallet($order->currency);

                if ($refundCashAmount > 0) {
                    // Refund to 'cleared' column
                    $wallet->credit($refundCashAmount, 'cleared');
                }
                $message = "Order canceled. Refunded " . $order->currency . " " . number_format($refundCashAmount, 2);
            } else {
                $holding = Portfolio::where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->first();

                if ($remainingUnits > 0 && $holding) {
                    // Refund back to cleared_quantity
                    $holding->increment('cleared_quantity', $remainingUnits);
                }
                $message = "Order canceled. Returned " . number_format($remainingUnits, 6) . " " . $order->symbol . " to portfolio.";
            }

            $order->update(['status' => 'canceled']);

            return response()->json([
                "success" => true,
                "message" => $message, 
                "data" => $order
            ]);
        });
    }
}