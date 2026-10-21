<?php

namespace App\Services\Reports;

use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestmentPlanReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    public function generate(Request $request): array
    {
        $query = FixedIncomeProduct::query()
            ->leftJoin('fixed_income_investments', 'fixed_income_products.id', '=', 'fixed_income_investments.fixed_income_product_id')
            ->select(
                'fixed_income_products.id',
                'fixed_income_products.name as market',
                'fixed_income_products.status',
                DB::raw('COUNT(fixed_income_investments.id) as total_investments'),
                DB::raw('COALESCE(SUM(fixed_income_investments.principal_amount), 0) as total_amount'),
                DB::raw('COALESCE(AVG(fixed_income_investments.principal_amount), 0) as average_investment'),
                DB::raw('COALESCE(SUM(fixed_income_investments.expected_interest), 0) as expected_roi')
            )
            ->groupBy('fixed_income_products.id', 'fixed_income_products.name', 'fixed_income_products.status');

        $this->applyPlanFilters($query, $request);

        $table = $query->orderByDesc('total_investments')->paginate($request->integer('per_page', 20));

        return $this->response(
            $this->summary(),
            $table,
            [$this->investmentChart(), $this->valueChart()],
            $request->all()
        );
    }

    public function summary(): array
    {
        return [
            'plans' => FixedIncomeProduct::count(),
            'active_plans' => FixedIncomeProduct::where('status', 'active')->count(),
            'inactive_plans' => FixedIncomeProduct::where('status', '!=', 'active')->count(),
            'investments' => FixedIncomeInvestment::count(),
            'principal' => (float) FixedIncomeInvestment::sum('principal_amount'),
            'expected_roi' => (float) FixedIncomeInvestment::sum('expected_interest'),
        ];
    }

    protected function investmentChart(): array
    {
        $rows = FixedIncomeInvestment::query()
            ->join('fixed_income_products', 'fixed_income_investments.fixed_income_product_id', '=', 'fixed_income_products.id')
            ->select('fixed_income_products.name', DB::raw('COUNT(*) as total'))
            ->groupBy('fixed_income_products.id', 'fixed_income_products.name')
            ->orderByDesc('total')
            ->get();

        return $this->chart('Investments Per Plan', $rows->pluck('name')->toArray(), $rows->pluck('total')->toArray());
    }

    protected function valueChart(): array
    {
        $rows = FixedIncomeInvestment::query()
            ->join('fixed_income_products', 'fixed_income_investments.fixed_income_product_id', '=', 'fixed_income_products.id')
            ->select('fixed_income_products.name', DB::raw('SUM(fixed_income_investments.principal_amount) as total'))
            ->groupBy('fixed_income_products.id', 'fixed_income_products.name')
            ->orderByDesc('total')
            ->get();

        return $this->chart('Investment Value', $rows->pluck('name')->toArray(), $rows->pluck('total')->toArray());
    }

    public function export(Request $request): array
    {
        $query = FixedIncomeProduct::query()
            ->leftJoin('fixed_income_investments', 'fixed_income_products.id', '=', 'fixed_income_investments.fixed_income_product_id')
            ->select(
                'fixed_income_products.id',
                'fixed_income_products.name as market',
                'fixed_income_products.status',
                DB::raw('COUNT(fixed_income_investments.id) as total_investments'),
                DB::raw('COALESCE(SUM(fixed_income_investments.principal_amount), 0) as total_amount'),
                DB::raw('COALESCE(AVG(fixed_income_investments.principal_amount), 0) as average_investment'),
                DB::raw('COALESCE(SUM(fixed_income_investments.expected_interest), 0) as expected_roi')
            )
            ->groupBy('fixed_income_products.id', 'fixed_income_products.name', 'fixed_income_products.status');

        $this->applyPlanFilters($query, $request);

        return $query->orderByDesc('total_investments')->get()->toArray();
    }

    protected function applySearch($query, string $search): void
    {
        $query->where('fixed_income_products.name', 'like', "%{$search}%");
    }

    protected function applyPlanFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('fixed_income_products.status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('fixed_income_investments.investment_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('fixed_income_investments.investment_date', '<=', $request->end_date);
        }
    }
}
