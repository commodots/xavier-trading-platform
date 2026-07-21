<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class FailedJob
{
    /**
     * Get count of failed jobs.
     */
    public static function count(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get recent failed jobs.
     */
    public static function recent(int $limit = 10): array
    {
        try {
            return DB::table('failed_jobs')
                ->latest()
                ->take($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}