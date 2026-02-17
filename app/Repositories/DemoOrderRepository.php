<?php

namespace App\Repositories;

use App\Models\DemoOrder;

class DemoOrderRepository
{
    public function create(array $data)
    {
        return DemoOrder::create($data);
    }

    public function getUserOrders($userId)
    {
        return DemoOrder::where('user_id', $userId)->get();
    }

    public function deleteUserOrders($userId)
    {
        return DemoOrder::where('user_id', $userId)->delete();
    }
}
