<?php

namespace App\Services\MatchingEngine;

use App\Models\Order;

class OrderBook
{
    public $buyOrders = [];
    public $sellOrders = [];

    public function add(Order $order): void
    {
        if ($order->side === 'buy') {
            $this->buyOrders[] = $order;
            usort($this->buyOrders, fn ($a, $b) => $b->price <=> $a->price);
        } else {
            $this->sellOrders[] = $order;
            usort($this->sellOrders, fn ($a, $b) => $a->price <=> $b->price);
        }
    }
}
