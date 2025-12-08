<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Wallet;
use Auth;

class OmsController extends Controller
{
    public function placeOrder(Request $request)
    {
        $request->validate([
            "market" => "required|in:NGX,GLOBAL,CRYPTO",
            "symbol" => "required",
            "company" => "required",
            "market_price" => "required|numeric",
            "amount" => "required|numeric|min:1"
        ]);

        $user = Auth::user();

        // Determine currency
        $currency = $request->market === "NGX" ? "NGN" : "USD";

        $wallet = Wallet::where('user_id', $user->id)
                        ->where('currency', $currency)
                        ->first();

        if ($wallet->balance < $request->amount)
            return response()->json(["success" => false, "message" => "Insufficient wallet balance"], 400);

        // Calculate units
        $units = $request->amount / $request->market_price;

        // Hold funds
        $wallet->decrement('balance', $request->amount);

        $order = Order::create([
            "user_id" => $user->id,
            "market" => $request->market,
            "symbol" => $request->symbol,
            "company" => $request->company,
            "market_price" => $request->market_price,
            "amount" => $request->amount,
            "units" => $units,
            "currency" => $currency,
            "status" => "pending_market"
        ]);

        return response()->json([
            "success" => true,
            "message" => "Order placed",
            "data" => $order
        ]);
    }

    public function listOrders()
    {
        return response()->json([
            "success" => true,
            "data" => Order::where('user_id', Auth::id())->orderByDesc('id')->get()
        ]);
    }
}

