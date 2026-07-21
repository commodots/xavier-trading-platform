<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralReportService
{
    public function summary(): array
    {
        
        return [
            ['label' => 'Total Referrals', 'value' => 0, 'icon' => 'users', 'color' => '#0047AB'],
            ['label' => 'Successful', 'value' => 0, 'icon' => 'check-circle', 'color' => '#10B981'],
            ['label' => 'Commission Paid', 'value' => 0, 'prefix' => '$', 'icon' => 'dollar-sign', 'color' => '#10B981'],
            ['label' => 'Outstanding Commission', 'value' => 0, 'prefix' => '$', 'icon' => 'clock', 'color' => '#F59E0B'],
        ];
    }

    public function list(array $filters = []): array
    {
        return [
            'data' => [],
            'total' => 0,
            'per_page' => 50,
            'current_page' => 1,
            'last_page' => 1,
        ];
    }
}