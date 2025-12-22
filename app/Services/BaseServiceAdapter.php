<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceConnection;
use Exception;

abstract class BaseServiceAdapter
{
    protected ServiceConnection $connection;

    public function __construct(Service $service)
    {
        // Sets the connection to the service's current active connection
        $this->connection = $service->activeConnection(); 
        
        if (!$this->connection) {
             throw new Exception("Active connection not found for service: " . $service->name);
        }
    }
    
    // All specific adapter methods will be defined here (e.g., placeOrder, processPayment)
}