<?php

namespace App\Services\Reports;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MaturityReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    /**
     * Main Report
     */
    public function generate(Request $request): array
    {
        $query = Order::query()
            ->with(['user:id,name,email']);

        $this->applyFilters($query, $request);

        $this->applySorting($query, $request);

        $table = $this->paginate($query, $request);

        return $this->response(
            $this->summary(),
            $table,
            [
                $this->monthlyChart(Order::class, 'created_at', 'id', 'count', 'Monthly Maturity'),
                $this->statusChart(Order::class, 'status', 'Investment Status'),
            ],
            $request->all()
        );
    }

    /**
     * Summary Cards
     */
    public function summary(): array
    {
        return [
            'today' => $this->today(),
            'seven_days' => $this->sevenDays(),
            'thirty_days' => $this->thirtyDays(),
            'overdue' => $this->overdue(),
            'completed' => $this->completed(),
            'pending' => $this->pending(),
        ];
    }

    /**
     * Due Today
     */
    protected function today()
    {
        return Order::whereDate(
            'created_at',
            Carbon::today()
        )->count();
    }

    /**
     * Due In 7 Days
     */
    protected function sevenDays()
    {
        return Order::whereBetween(
            'created_at',
            [
                Carbon::today(),
                Carbon::today()->addDays(7),
            ]
        )->count();
    }

    /**
     * Due In 30 Days
     */
    protected function thirtyDays()
    {
        return Order::whereBetween(
            'created_at',
            [
                Carbon::today(),
                Carbon::today()->addDays(30),
            ]
        )->count();
    }

    /**
     * Overdue
     */
    protected function overdue()
    {
        return Order::where(
            'created_at',
            '<',
            Carbon::today()->subDays(30)
        )
            ->whereIn('status', ['open', 'pending'])
            ->count();
    }

    /**
     * Completed
     */
    protected function completed()
    {
        return Order::where(
            'status',
            'filled'
        )->count();
    }

    /**
     * Pending
     */
    protected function pending()
    {
        return Order::where(
            'status',
            'pending'
        )->count();
    }

    /**
     * Export
     */
    public function export(Request $request)
    {
        $query = Order::query()
            ->with(['user:id,name,email']);

        $this->applyFilters($query, $request);

        return $this->exportCollection($query->get());
    }

    /**
     * Search
     */
    protected function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->whereHas('user', function ($user) use ($search) {
                $user->where('name', 'like', "%{$search}%");
            })
                ->orWhere('symbol', 'like', "%{$search}%")
                ->orWhere('market', 'like', "%{$search}%");
        });
    }
}
