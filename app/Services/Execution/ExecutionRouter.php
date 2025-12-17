<?php

namespace App\Services\Execution;

use App\Models\Order;

class ExecutionRouter
{
    public static function route(Order $order)
    {
        return match (config('services.ngx.mode')) {
            'live' => app(NgxLiveAdapter::class)->send($order),
            'test' => app(NgxTestAdapter::class)->send($order),
            'dummy' => app(NgxDummyAdapter::class)->send($order),
        };
    }
}
