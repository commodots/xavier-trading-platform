<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Services\Reports\FailedJob;

class SystemReportService
{
    protected ReportCacheService $cache;

    public function __construct(ReportCacheService $cache)
    {
        $this->cache = $cache;
    }

    public function all(): array
    {
        return $this->cache->remember('system.all', function () {
            return [
                'cards' => $this->cards(),
                'integrations' => $this->integrationHealth(),
                'logs' => $this->logs(),
            ];
        }, 120); // 2 minute cache
    }

    public function cards(): array
    {
        $emailsSent = 0;
        try {
            $emailsSent = DB::table('email_logs')->count();
        } catch (\Exception $e) {
            // Table doesn't exist yet
        }

        return [
            ['label' => 'Users Online', 'value' => User::where('last_active_at', '>=', now()->subMinutes(15))->count(), 'icon' => 'users', 'color' => '#10B981'],
            ['label' => 'Failed Jobs', 'value' => FailedJob::count(), 'icon' => 'alert-triangle', 'color' => '#EF4444'],
            ['label' => 'Queued Jobs', 'value' => DB::table('jobs')->count(), 'icon' => 'activity', 'color' => '#F59E0B'],
            ['label' => 'Storage Used', 'value' => $this->formatBytes($this->getDirectorySize(storage_path())), 'icon' => 'database', 'color' => '#0047AB'],
            ['label' => 'Emails Sent', 'value' => $emailsSent, 'icon' => 'mail', 'color' => '#8B5CF6'],
            ['label' => 'Notifications Sent', 'value' => \App\Models\Notification::count(), 'icon' => 'bell', 'color' => '#10B981'],
            ['label' => 'API Status', 'value' => 'Operational', 'icon' => 'shield', 'color' => '#10B981'],
            ['label' => 'Cron Status', 'value' => Cache::has('cron_last_run') ? 'Running' : 'Unknown', 'icon' => 'zap', 'color' => '#F59E0B'],
        ];
    }

    public function logs(): array
    {
        return [
            'errors' => TransactionAudit::where('action', 'error')->latest()->take(10)->get()->map(fn($l) => [
                'message' => $l->action,
                'time' => $l->created_at?->format('Y-m-d H:i'),
            ]),
            'warnings' => TransactionAudit::where('action', 'warning')->latest()->take(10)->get()->map(fn($l) => [
                'message' => $l->action,
                'time' => $l->created_at?->format('Y-m-d H:i'),
            ]),
            'payment_failures' => Transaction::where('status', 'failed')->latest()->take(10)->get()->map(fn($t) => [
                'message' => 'Payment failed: ' . ($t->reference ?? $t->id),
                'time' => $t->created_at?->format('Y-m-d H:i'),
            ]),
            'login_attempts' => TransactionAudit::where('entity_type', 'login')->latest()->take(10)->get()->map(fn($l) => [
                'message' => 'Login from IP: ' . ($l->ip_address ?? 'unknown'),
                'time' => $l->created_at?->format('Y-m-d H:i'),
            ]),
        ];
    }

    public function integrationHealth(): array
    {
        return [
            ['name' => 'Fincra', 'status' => 'connected', 'icon' => '🟢'],
            ['name' => 'NGX Broker', 'status' => 'connected', 'icon' => '🟢'],
            ['name' => 'Email', 'status' => 'connected', 'icon' => '🟢'],
            ['name' => 'SMS', 'status' => 'degraded', 'icon' => '🟡'],
            ['name' => 'Push Notifications', 'status' => 'connected', 'icon' => '🟢'],
        ];
    }

    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }

    protected function getDirectorySize($path): int
    {
        $size = 0;
        foreach (glob(rtrim($path, '/') . '/*', GLOB_NOSORT) as $file) {
            $size += is_file($file) ? filesize($file) : $this->getDirectorySize($file);
        }
        return $size;
    }
}