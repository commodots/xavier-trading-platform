<?php 
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;

class OrderController extends Controller
{
	public function store(Request $request)
	{
		$order = Order::create($request->validated());

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
