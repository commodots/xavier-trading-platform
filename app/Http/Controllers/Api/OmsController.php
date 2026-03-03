<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\LiveTradingService;
use App\Services\Demo\DemoTradingService;
use App\Models\Demo\{DemoWallet, DemoTransaction, DemoLedger, DemoOrder, DemoPortfolio};
use App\Models\{NewTransaction, Wallet, Ledger, Order, Portfolio};
use Illuminate\Support\Facades\DB;
class OmsController extends Controller
{
    private function resolveModels($user)
    {
        $isDemo = $user->trading_mode === 'demo';
        return (object) [
            'isDemo'      => $isDemo,
            'wallet'      => $isDemo ? DemoWallet::class : Wallet::class,
            'transaction' => $isDemo ? DemoTransaction::class : NewTransaction::class,
            'ledger'      => $isDemo ? DemoLedger::class : Ledger::class,
            'order'       => $isDemo ? DemoOrder::class : Order::class,
            'portfolio'   => $isDemo ? DemoPortfolio::class : Portfolio::class,
        ];
    }

    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            "market" => "required|in:NGX,GLOBAL,CRYPTO",
            "symbol" => "required",
            "company" => "required",
            "market_price" => "required|numeric",
            "amount" => "required|numeric|min:1",
            "side" => "required|in:buy,sell"
        ]);

        $user = auth()->user();
        
        $service = ($user->trading_mode === 'demo') 
            ? app(DemoTradingService::class) 
            : app(LiveTradingService::class);

        try {
            $order = $service->executeTrade($user, $data);
            return response()->json(["success" => true, "data" => $order]);
        } catch (\Exception $e) {
            return response()->json(["success" => false, "message" => $e->getMessage()], 400);
        }
    }

    public function listOrders()
    {
        $user = auth()->user();
        $models = $this->resolveModels($user);

        $orderClass = $models->order;

        return response()->json([
            "success" => true,
            "data" => $orderClass::where('user_id', $user->id)
                ->orderByDesc('id')
                ->get()
        ]);
    }

    public function cancelOrder($id)
    {
        $user = auth()->user();
        $models = $this->resolveModels($user);
        
        $orderClass = $models->order;
        $walletClass = $models->wallet;
        $portfolioClass = $models->portfolio;

        return DB::transaction(function () use ($id, $user, $orderClass, $walletClass, $portfolioClass) {
            
            $order = $orderClass::where('id', $id)
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

            
            $wallet = $walletClass::where('user_id', $user->id)->where('currency', $order->currency)->lockForUpdate()->first();
            
            $clearedCol = $order->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $unclearedCol = $order->currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';
            
           
            $holding = $portfolioClass::where('user_id', $user->id)->where('symbol', $order->symbol)->lockForUpdate()->first();

            if ($order->side === 'buy') {
                if ($refundCashAmount > 0 && $wallet) {
                    $wallet->decrement('locked', $refundCashAmount);
                    $wallet->increment($clearedCol, $refundCashAmount);
                }
                if ($holding && $remainingUnits > 0) {
                    $holding->decrement('quantity', $remainingUnits);
                    $holding->decrement('uncleared_quantity', $remainingUnits);
                }
                $message = "Order canceled. Refunded " . $order->currency . " " . number_format($refundCashAmount, 2);
            } else {
                if ($remainingUnits > 0 && $holding) {
                    $holding->decrement('uncleared_quantity', $remainingUnits);
                    $holding->increment('cleared_quantity', $remainingUnits);
                }
                if ($refundCashAmount > 0 && $wallet) {
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