<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Cache;

class ReportCacheService
{
    protected string $prefix;
    protected int $ttl;

    public function __construct()
    {
        $this->prefix = config('reporting.cache.prefix', 'report');
        $this->ttl = config('reporting.cache.default_ttl', 300); // 5 minutes default
    }

    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        if (!config('reporting.cache.enabled', true)) {
            return $callback();
        }

        return Cache::remember(
            $this->prefix . ':' . $key,
            $ttl ?? $this->ttl,
            $callback
        );
    }

    public function forget(string $key): void
    {
        Cache::forget($this->prefix . ':' . $key);
    }

    public function flush(): void
    {
        // Only flushes keys with our prefix pattern
        $pattern = $this->prefix . ':*';
        // Implementation depends on cache driver
    }
}