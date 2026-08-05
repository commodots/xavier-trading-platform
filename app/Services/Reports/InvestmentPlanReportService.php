<?php

namespace App\Services\Reports;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestmentPlanReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    /**
     * Main Report
     */
    public function generate(Request $request): array
    {
        $query = Order::query()
            ->select(
                'market',
                DB::raw('COUNT(*) as total_investments'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('AVG(amount) as average_investment')
            )
            ->whereNotNull('market')
            ->groupBy('market');

        $this->applyFilters($query, $request);

        $this->applySorting($query, $request);

        // Override sort to use aggregate columns safely
        $query->reorder('total_investments', 'desc');

        $table = $this->paginate($query, $request);

        return $this->response(
            $this->summary(),
            $table,
            [
                $this->investmentChart(),
                $this->valueChart(),
            ],
            $request->all()
        );
    }

    /**
     * Summary
     */
    public function summary(): array
    {
        return [
            'plans' => Order::select('market')->distinct()->whereNotNull('market')->count(),
            'active_plans' => Order::select('market')->distinct()->whereNotNull('market')->whereIn('status', ['open', 'pending'])->count(),
            'inactive_plans' => Order::select('market')->distinct()->whereNotNull('market')->where('status', 'cancelled')->count(),
            'investments' => Order::count(),
            'principal' => Order::sum(DB::raw('amount * price')),
            'expected_roi' => 0,
        ];
    }

    /**
     * Investments Per Plan Chart
     */
    protected function investmentChart(): array
    {
        $rows = Order::select('market', DB::raw('COUNT(*) as total'))
            ->whereNotNull('market')
            ->groupBy('market')
            ->orderBy('total', 'desc')
            ->get();

        return $this->chart(
            'Investments Per Plan',
            $rows->pluck('market')->toArray(),
            $rows->pluck('total')->toArray()
        );
    }

    /**
     * Investment Value Per Plan Chart
     */
    protected function valueChart(): array
    {
        $rows = Order::select('market', DB::raw('SUM(amount) as total'))
            ->whereNotNull('market')
            ->groupBy('market')
            ->orderBy('total', 'desc')
            ->get();

        return $this->chart(
            'Investment Value',
            $rows->pluck('market')->toArray(),
            $rows->pluck('total')->toArray()
        );
    }

    /**
     * ROI Per Plan Chart
     */
    protected function roiChart(): array
    {
        $rows = Order::select('market', DB::raw('COUNT(*) as roi'))
            ->whereNotNull('market')
            ->whereIn('status', ['open', 'pending'])
            ->groupBy('market')
            ->get();

        return $this->chart(
            'ROI By Plan',
            $rows->pluck('market')->toArray(),
            $rows->pluck('roi')->toArray()
        );
    }

    /**
     * Export
     */
    public function export(Request $request)
    {
        return Order::select('market', DB::raw('COUNT(*) as total_investments'), DB::raw('SUM(amount) as total_amount'))
            ->whereNotNull('market')
            ->groupBy('market')
            ->get()
            ->toArray();
    }

    /**
     * Search
     */
    protected function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('market', 'like', "%{$search}%");
        });
    }
}
