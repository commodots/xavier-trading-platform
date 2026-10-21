<?php

namespace App\Services\Reports;

use App\Models\FixedIncomeInvestment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MaturityReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    public function generate(Request $request): array
    {
        $query = FixedIncomeInvestment::query()
            ->with(['user:id,name,email', 'product:id,name']);

        $this->applyFilters($query, $request, 'maturity_date');
        $this->applySorting($query, $request, 'maturity_date');

        return $this->response(
            $this->summary($request),
            $this->paginate($query, $request),
            [
                $this->monthlyChart(FixedIncomeInvestment::class, 'maturity_date', 'id', 'count', 'Monthly Maturities'),
                $this->statusChart(FixedIncomeInvestment::class, 'status', 'Maturity Status'),
            ],
            $request->all()
        );
    }

    public function summary(Request $request): array
    {
        $query = FixedIncomeInvestment::query();
        $this->applyFilters($query, $request, 'maturity_date');
        $openStatuses = ['active', 'maturing', 'matured'];

        return [
            'today' => (clone $query)->whereDate('maturity_date', Carbon::today())->count(),
            'seven_days' => (clone $query)->whereBetween('maturity_date', [Carbon::today(), Carbon::today()->addDays(7)])->whereIn('status', $openStatuses)->count(),
            'thirty_days' => (clone $query)->whereBetween('maturity_date', [Carbon::today(), Carbon::today()->addDays(30)])->whereIn('status', $openStatuses)->count(),
            'overdue' => (clone $query)->where('maturity_date', '<', Carbon::today())->whereIn('status', $openStatuses)->count(),
            'completed' => (clone $query)->where('status', 'redeemed')->count(),
            'pending' => (clone $query)->whereIn('status', ['pending', 'pending_execution'])->count(),
        ];
    }

    public function export(Request $request): array
    {
        $query = FixedIncomeInvestment::query()
            ->with(['user:id,name,email', 'product:id,name']);

        $this->applyFilters($query, $request, 'maturity_date');

        return $query->orderBy('maturity_date')->get()->toArray();
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
