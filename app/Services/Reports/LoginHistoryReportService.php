<?php

namespace App\Services\Reports;

use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginHistoryReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    /**
     * Main Report
     */
    public function generate(Request $request): array
    {
        $query = LoginHistory::query()
            ->with(['user:id,name,email']);

        $this->applyFilters($query, $request, 'logged_in_at');

        $this->applySorting($query, $request, 'logged_in_at');

        $table = $this->paginate($query, $request);

        return $this->response(
            $this->summary(),
            $table,
            [
                $this->dailyLoginChart(),
                $this->successRateChart(),
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
            'successful' => LoginHistory::where('successful', true)->count(),
            'failed' => LoginHistory::where('successful', false)->count(),
            'locked' => LoginHistory::where('successful', false)
                ->where('logged_in_at', '>=', now()->subDay())
                ->count(),
            'suspicious' => LoginHistory::where('successful', false)
                ->where('logged_in_at', '>=', now()->subDay())
                ->count(),
            'total' => LoginHistory::count(),
        ];
    }

    /**
     * Daily Login Chart
     */
    protected function dailyLoginChart(): array
    {
        $rows = LoginHistory::select(
            DB::raw('DATE(logged_in_at) date'),
            DB::raw('COUNT(*) total')
        )
            ->where('logged_in_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dates = [];
        $successData = [];
        $failedData = [];

        $successRows = LoginHistory::select(
            DB::raw('DATE(logged_in_at) date'),
            DB::raw('COUNT(*) total')
        )
            ->where('successful', true)
            ->where('logged_in_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $failedRows = LoginHistory::select(
            DB::raw('DATE(logged_in_at) date'),
            DB::raw('COUNT(*) total')
        )
            ->where('successful', false)
            ->where('logged_in_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dates[] = now()->subDays($i)->format('M d');
            $successData[] = (int) ($successRows[$date] ?? 0);
            $failedData[] = (int) ($failedRows[$date] ?? 0);
        }

        return [
            'title' => 'Daily Logins',
            'labels' => $dates,
            'values' => $successData,
            'failed' => $failedData,
        ];
    }

    /**
     * Success Rate Chart
     */
    protected function successRateChart(): array
    {
        $successful = LoginHistory::where('successful', true)->count();
        $failed = LoginHistory::where('successful', false)->count();

        return $this->chart(
            'Login Success Rate',
            ['Successful', 'Failed'],
            [$successful, $failed]
        );
    }

    /**
     * Success
     */
    protected function success()
    {
        return LoginHistory::where('successful', true)->count();
    }

    /**
     * Failed
     */
    protected function failed()
    {
        return LoginHistory::where('successful', false)->count();
    }

    /**
     * Locked
     */
    protected function locked()
    {
        return LoginHistory::where('successful', false)
            ->where('logged_in_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * Suspicious
     */
    protected function suspicious()
    {
        return LoginHistory::where('successful', false)
            ->where('logged_in_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * Export
     */
    public function export(Request $request)
    {
        $query = LoginHistory::query()
            ->with(['user:id,name,email']);

        $this->applyFilters($query, $request, 'logged_in_at');

        return $this->exportCollection($query->get());
    }

    /**
     * Search
     */
    protected function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->whereHas('user', function ($user) use ($search) {
                $user->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
                ->orWhere('ip_address', 'like', "%{$search}%")
                ->orWhere('device', 'like', "%{$search}%")
                ->orWhere('browser', 'like', "%{$search}%");
        });
    }
}
