<?php

namespace App\Services\Execution;

use App\Models\Order;
use App\Services\MatchingEngine\MatchingEngine;

class NgxDummyAdapter
{
    public function send(Order $order)
    {
        // Immediate matching locally
        app(MatchingEngine::class)->match($order);
		if (config('app.simulate_errors')) {
			throw new \Exception('NGX gateway timeout');
		}

        return [
            'status' => 'accepted',
            'mode' => 'dummy',
        ];
    }
}
