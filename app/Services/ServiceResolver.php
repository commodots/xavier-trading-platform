<?php

namespace App\Services;

use App\Models\Service;
use Exception;

class ServiceResolver
{
    public static function resolve($type): BaseServiceAdapter
    {
        // Find the single active service based on type (e.g., 'ngx')
        $service = Service::where('type', $type)->where('is_active', true)->first(); 
        
        if (!$service) {
             throw new Exception("Service type '{$type}' is not active or not configured.");
        }

        return match ($type) {
            'ngx' => new NgxAdapter($service), 
            'payment' => new PaymentAdapter($service), 
            default => throw new Exception("Service type '{$type}' not configured"), 
        };
    }
}