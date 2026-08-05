<?php

namespace App\Services\Reports\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait InteractsWithCharts
{
    /**
     * Build a monthly aggregation chart.
     */
    protected function monthlyChart(
        string $model,
        string $column = 'created_at',
        string $valueColumn = 'id',
        string $aggregate = 'count',
        string $title = 'Monthly Trend'
    ): array {
        $rows = $model::select(
            DB::raw("MONTH({$column}) month"),
            DB::raw("{$aggregate}({$valueColumn}) total")
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $this->chart(
            $title,
            $rows->pluck('month')->toArray(),
            $rows->pluck('total')->toArray()
        );
    }

    /**
     * Build a status distribution chart.
     */
    protected function statusChart(
        string $model,
        string $statusColumn = 'status',
        string $title = 'Status Distribution'
    ): array {
        $rows = $model::select(
            $statusColumn,
            DB::raw('COUNT(*) total')
        )
            ->groupBy($statusColumn)
            ->get();

        return $this->chart(
            $title,
            $rows->pluck($statusColumn)->toArray(),
            $rows->pluck('total')->toArray()
        );
    }

    /**
     * Build a category/grouped chart.
     */
    protected function categoryChart(
        Builder $query,
        string $groupColumn,
        string $valueColumn,
        string $aggregate = 'SUM',
        string $title = 'Category Breakdown'
    ): array {
        $rows = (clone $query)
            ->select(
                $groupColumn,
                DB::raw("{$aggregate}({$valueColumn}) total")
            )
            ->groupBy($groupColumn)
            ->get();

        return $this->chart(
            $title,
            $rows->pluck($groupColumn)->toArray(),
            $rows->pluck('total')->toArray()
        );
    }

    /**
     * Build a daily aggregation chart.
     */
    protected function dailyChart(
        string $model,
        string $column = 'created_at',
        string $valueColumn = 'id',
        string $aggregate = 'count',
        string $title = 'Daily Trend',
        int $days = 30
    ): array {
        $rows = $model::select(
            DB::raw("DATE({$column}) date"),
            DB::raw("{$aggregate}({$valueColumn}) total")
        )
            ->where($column, '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->chart(
            $title,
            $rows->pluck('date')->toArray(),
            $rows->pluck('total')->toArray()
        );
    }
}
