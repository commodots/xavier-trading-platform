<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demo\DemoLedger;
use App\Models\Demo\DemoOrder;
use App\Models\Demo\DemoPortfolio;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoWallet;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\Order;
use App\Models\Portfolio;
use App\Models\Wallet;
use App\Services\Demo\DemoTradingService;
use App\Services\LiveTradingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OmsController extends Controller
{
    use AuthorizesRequests;

    private function resolveModels($user)
    {
        $isDemo = $user->trading_mode === 'demo';

        return (object) [
            'isDemo' => $isDemo,
            'wallet' => $isDemo ? DemoWallet::class : Wallet::class,
            'transaction' => $isDemo ? DemoTransaction::class : NewTransaction::class,
            'ledger' => $isDemo ? DemoLedger::class : Ledger::class,
            'order' => $isDemo ? DemoOrder::class : Order::class,
            'portfolio' => $isDemo ? DemoPortfolio::class : Portfolio::class,
        ];
    }

    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'market' => 'required|in:NGX,GLOBAL,CRYPTO,FIXED_INCOME',
            'symbol' => 'required',
            'company' => 'required',
            'market_price' => 'required|numeric',
            'amount' => 'required|numeric|min:1',
            'side' => 'required|in:buy,sell',
        ]);

        $user = Auth::user();

        // RBAC: Check user can trade
        $this->authorize('create', Order::class);

        // PSD2: Verify user completed KYC + trading allowed
        if (! $user->kyc_verified || $user->email_verified_at === null) {
            return response()->json([
                'success' => false,
                'message' => 'KYC verification and email verification required',
            ], 422);
        }

        // Rate-limiting already applied via middleware
        $data['market'] = strtoupper($data['market']);

        $service = ($user->trading_mode === 'demo')
            ? app(DemoTradingService::class)
            : app(LiveTradingService::class);

        try {
            $order = $service->executeTrade($user, $data);

            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            Log::error('Trade Failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Unable to place order at this time. Please try again later.'], 500);
        }
    }

    public function listOrders(Request $request)
    {
        $user = Auth::user();
        $models = $this->resolveModels($user);

        $orderClass = $models->order;

        return response()->json([
            'success' => true,
            'data' => $orderClass::where('user_id', $user->id)->orderByDesc('id')->get(),
        ]);
    }

    public function cancelOrder($id)
    {
        $user = Auth::user();
        $models = $this->resolveModels($user);

        $orderClass = $models->order;
        $order = $orderClass::find($id);

        // RBAC: User can only cancel their own orders
        $this->authorize('cancel', $order);

        $walletClass = $models->wallet;
        $portfolioClass = $models->portfolio;

        return DB::transaction(function () use ($id, $user, $orderClass, $walletClass, $portfolioClass) {

            $order = $orderClass::where('id', $id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            $cancellableStatuses = ['open', 'pending_market', 'partially_filled'];
            if (! in_array($order->status, $cancellableStatuses)) {
                return response()->json(['success' => false, 'message' => "This order is already {$order->status}."], 400);
            }

            $filledUnits = (float) ($order->filled_quantity ?? 0);
            $totalUnits = (float) $order->units;
            $remainingUnits = $totalUnits - $filledUnits;

            $costPerUnit = (float) $order->price;
            $refundCashAmount = $remainingUnits * $costPerUnit;

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
                } else {
                    // Sell Order Cancellation: Return the assets to the user
                    if ($remainingUnits > 0 && $holding) {
                        $holding->decrement('uncleared_quantity', $remainingUnits);
                        $holding->increment('cleared_quantity', $remainingUnits);
                    }
                    if ($refundCashAmount > 0 && $wallet) {
                        $wallet->decrement($unclearedCol, $refundCashAmount);
                    }
                }
                $message = 'Order canceled. Refunded '.$order->currency.' '.number_format($refundCashAmount, 2);
            } else {
                if ($remainingUnits > 0 && $holding) {
                    $holding->decrement('uncleared_quantity', $remainingUnits);
                    $holding->increment('cleared_quantity', $remainingUnits);
                }
                if ($refundCashAmount > 0 && $wallet) {
                    $wallet->decrement($unclearedCol, $refundCashAmount);
                }
                $message = 'Order canceled. Returned '.number_format($remainingUnits, 6).' '.$order->symbol.' to portfolio.';
            }

            $order->update(['status' => 'canceled']);

            $order->trades()->where('settlement_status', 'pending')->update(['settlement_status' => 'canceled']);

            return response()->json(['success' => true, 'message' => $message, 'data' => $order]);
        });
    }
}
