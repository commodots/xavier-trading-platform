<?php

namespace App\Services\Reports;

use App\Models\FixedIncomeInvestment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ROIReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    public function generate(Request $request): array
    {
        $query = FixedIncomeInvestment::query()
            ->with(['user:id,name,email', 'product:id,name']);

        $this->applyFilters($query, $request, 'investment_date');
        $this->applySorting($query, $request, 'investment_date');

        return $this->response(
            $this->summary($request),
            $this->paginate($query, $request),
            [
                $this->monthlyChart(FixedIncomeInvestment::class, 'investment_date', 'expected_interest', 'sum', 'Expected ROI'),
                $this->statusChart(FixedIncomeInvestment::class, 'status', 'Investment Status'),
            ],
            $request->all()
        );
    }

    public function summary(Request $request): array
    {
        $query = FixedIncomeInvestment::query();
        $this->applyFilters($query, $request, 'investment_date');

        $expected = (float) (clone $query)->sum('expected_interest');
        $paid = (float) (clone $query)->sum('actual_interest');
        $upcomingQuery = (clone $query)->whereBetween('maturity_date', [Carbon::today(), Carbon::today()->addDays(30)]);
        $overdueQuery = (clone $query)->where('maturity_date', '<', Carbon::today());

        return [
            'expected_roi' => $expected,
            'paid_roi' => $paid,
            'outstanding_roi' => max(0, $expected - $paid),
            'active_roi' => (clone $query)->whereIn('status', ['active', 'pending_execution', 'maturing'])->count(),
            'completed_roi' => (clone $query)->where('status', 'redeemed')->count(),
            'overdue_roi' => $overdueQuery->whereIn('status', ['active', 'maturing', 'matured'])->count(),
            'upcoming_roi' => $upcomingQuery->whereIn('status', ['active', 'maturing'])->count(),
        ];
    }

    public function export(Request $request): array
    {
        $query = FixedIncomeInvestment::query()
            ->with(['user:id,name,email', 'product:id,name']);

        $this->applyFilters($query, $request, 'investment_date');

        return $query->orderByDesc('investment_date')->get()->toArray();
    }

    protected function applySearch($query, string $search): void
    {
        $query->where(function ($investment) use ($search) {
            $investment->whereHas('user', function ($user) use ($search) {
                $user->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('product', function ($product) use ($search) {
                $product->where('name', 'like', "%{$search}%");
            });
        });
    }
}
