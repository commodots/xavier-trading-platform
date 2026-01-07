<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Order;
use App\Services\ContractNotes\ContractNoteService;
use App\Services\Portfolio\PortfolioService;
use App\Services\MatchingEngine\MatchingEngine;
use App\Services\Audit\AuditLogger;
use App\Models\ActivityLog;

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


		try {
			$action = strtoupper($order->type);
			ActivityLog::create([
				'user_id'    => auth()->id(),
				'activity'   => 'Order Placed',
				'details'    => "Placed a {$action} order for {$order->units} units of {$order->symbol} ({$order->company}) at {$order->currency} {$order->amount}",
				'ip_address' => $request->ip(),
				'user_agent' => $request->userAgent(),
			]);
		} catch (\Throwable $e) {
		}

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
