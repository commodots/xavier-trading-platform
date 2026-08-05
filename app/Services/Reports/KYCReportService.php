<?php

namespace App\Services\Reports;

use App\Models\KycProfile;
use Illuminate\Http\Request;

class KYCReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    /**
     * Main Report
     */
    public function generate(Request $request): array
    {
        $query = KycProfile::query()
            ->with(['user:id,name,email']);

        $this->applyFilters($query, $request);

        $this->applySorting($query, $request);

        $table = $this->paginate($query, $request);

        return $this->response(
            $this->summary(),
            $table,
            [
                $this->statusChart(KycProfile::class, 'status', 'KYC Status Distribution'),
                $this->monthlyChart(KycProfile::class, 'created_at', 'id', 'count', 'Monthly KYC Submissions'),
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
            'pending' => KycProfile::where('status', 'pending')->count(),
            'approved' => KycProfile::whereIn('status', ['approved', 'verified'])->count(),
            'rejected' => KycProfile::where('status', 'rejected')->count(),
            'expired' => KycProfile::where('status', 'expired')->count(),
            'total' => KycProfile::count(),
            'approval_rate' => $this->approvalRate(),
        ];
    }

    /**
     * Approval Rate
     */
    protected function approvalRate(): float
    {
        $total = KycProfile::count();
        if ($total === 0) {
            return 0;
        }

        $approved = KycProfile::whereIn('status', ['approved', 'verified'])->count();

        return round(($approved / $total) * 100, 2);
    }

    /**
     * Export
     */
    public function export(Request $request)
    {
        $query = KycProfile::query()
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
                $user->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });
    }
}
