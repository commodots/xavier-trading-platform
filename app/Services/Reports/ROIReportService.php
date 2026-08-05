<?php

namespace App\Services\Reports;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ROIReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    /**
     * Generate ROI Report
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
                $this->monthlyChart(Order::class, 'created_at', 'id', 'count', 'Monthly ROI'),
                $this->statusChart(Order::class, 'status', 'ROI Status'),
            ],
            $request->all()
        );
    }

    /**
     * ROI Summary
     */
    public function summary(): array
    {
        $expected = 0;
        $paid = 0;

        return [
            'expected_roi' => $expected,
            'paid_roi' => $paid,
            'outstanding_roi' => ($expected - $paid),
            'active_roi' => Order::whereIn('status', ['open', 'pending'])->count(),
            'completed_roi' => Order::where('status', 'filled')->count(),
            'overdue_roi' => $this->overdue(),
            'upcoming_roi' => $this->upcoming(),
        ];
    }

    /**
     * ROI Due Within 30 Days
     */
    protected function upcoming()
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
     * Overdue ROI
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
