<?php 
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Order;

class OrderController extends Controller
{
	public function store(Request $request)
	{
		$validated = $request->validate([
        'symbol'       => 'required|string',
        'company'      => 'required|string',
        'units'        => 'required|numeric|min:0',
        'amount'       => 'required|numeric|min:0',
        'type'         => 'required|in:buy,sell',
        'market'       => 'required|string',
        'market_price' => 'required|numeric',
        'currency'     => 'required|string',
    ]);

		$validated['user_id'] = auth()->id();
    $validated['status'] = 'open';

		$order = Order::create($validated);

		app(MatchingEngine::class)->process($order);

		app(SettlementService::class)->settle($order);

		app(PortfolioService::class)->post($order);

		app(ContractNoteService::class)->generate($order);

		AuditLogger::log('ORDER_PROCESSED', ['order_id' => $order->id]);

		return response()->json($order->fresh());
	}
	public function show(Order $order)
	{
		$order->load([
			'trades.settlement'
		]);

		return response()->json($order);
	}
}
