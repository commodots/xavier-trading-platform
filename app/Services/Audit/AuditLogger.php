<?php

namespace App\Services\Audit;

class AuditLogger
{
    public static function log($event, $payload)
    {
        \Log::channel('audit')->info($event, $payload);
    }
}
