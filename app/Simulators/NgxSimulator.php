<?php 
class NgxSimulator
{
    public static function submitOrder($order)
    {
        if (config('services.ngx.mode') === 'dummy') {
            if (rand(1, 10) <= 2) {
                throw new \Exception("NGX Timeout Simulation");
            }
        }

        return [
            'status' => 'matched',
            'filled_qty' => $order->qty
        ];
    }
}