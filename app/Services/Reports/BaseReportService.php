<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseReportService
{
    /**
     * Apply standard report filters.
     */
    protected function applyFilters(
        Builder $query,
        Request $request,
        string $dateColumn = 'created_at'
    ): Builder {

        // Date Range
        if ($request->filled('start_date')) {
            $query->whereDate(
                $dateColumn,
                '>=',
                Carbon::parse($request->start_date)
            );
        }

        if ($request->filled('end_date')) {
            $query->whereDate(
                $dateColumn,
                '<=',
                Carbon::parse($request->end_date)
            );
        }

        // Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $this->applySearch(
                $query,
                $request->search
            );
        }

        return $query;
    }

    /**
     * Override in child classes.
     */
    protected function applySearch(
        Builder $query,
        string $search
    ): void {}

    /**
     * Apply sorting.
     */
    protected function applySorting(
        Builder $query,
        Request $request,
        string $defaultColumn = 'created_at'
    ): Builder {

        $sortBy = $request->get(
            'sort_by',
            $defaultColumn
        );

        $direction = $request->get(
            'direction',
            'desc'
        );

        return $query->orderBy(
            $sortBy,
            $direction
        );
    }

    /**
     * Paginate.
     */
    protected function paginate(
        Builder $query,
        Request $request
    ): LengthAwarePaginator {

        return $query->paginate(
            $request->integer(
                'per_page',
                20
            )
        )->withQueryString();
    }

    /**
     * Standard response.
     */
    protected function response(
        array $summary,
        $table,
        array $charts = [],
        array $filters = []
    ): array {

        return [

            'summary' => $summary,

            'table' => $table,

            'charts' => $charts,

            'filters' => $filters,

        ];
    }

    /**
     * Date presets.
     */
    protected function presets(): array
    {
        return [

            'today' => [
                Carbon::today(),
                Carbon::today(),
            ],

            'week' => [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ],

            'month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ],

            'quarter' => [
                Carbon::now()->startOfQuarter(),
                Carbon::now()->endOfQuarter(),
            ],

            'year' => [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
            ],

        ];
    }

    /**
     * Currency formatter.
     */
    protected function money($amount): string
    {
        return number_format(
            $amount,
            2
        );
    }

    /**
     * Percentage formatter.
     */
    protected function percent($value): string
    {
        return number_format(
            $value,
            2
        ).'%';
    }

    /**
     * Empty chart structure.
     */
    protected function chart(
        string $title,
        array $labels,
        array $values
    ): array {

        return [

            'title' => $title,

            'labels' => $labels,

            'values' => $values,

        ];
    }

    /**
     * Export data.
     */
    protected function exportCollection($collection): array
    {
        return $collection
            ->map(function ($item) {
                return $item;
            })
            ->toArray();
    }
}
