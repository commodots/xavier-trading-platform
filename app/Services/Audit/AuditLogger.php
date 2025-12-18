<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public static function log(string $event, array $data = []): void
    {
        Log::channel('audit')->info($event, $data);
    }
}
