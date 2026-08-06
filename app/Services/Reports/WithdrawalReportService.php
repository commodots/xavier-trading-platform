<?php

namespace App\Services\Reports;

use App\Models\WithdrawalRequest;

class WithdrawalReportService
{
    public function summary(): array
    {
        $ngnWithdrawals = WithdrawalRequest::where('currency', 'NGN')->where('status', 'approved')->sum('amount');
        $usdWithdrawals = WithdrawalRequest::where('currency', 'USD')->where('status', 'approved')->sum('amount');

        return [
            ['label' => 'Pending', 'value' => WithdrawalRequest::where('status', 'pending')->count()],
            ['label' => 'Approved', 'value' => WithdrawalRequest::where('status', 'approved')->count()],
            ['label' => 'Rejected', 'value' => WithdrawalRequest::where('status', 'rejected')->count()],
            ['label' => 'NGN Paid Today', 'value' => WithdrawalRequest::where('currency', 'NGN')->where('status', 'approved')
                ->whereDate('created_at', today())->sum('amount'), 'prefix' => '₦'],
            ['label' => 'USD Paid Today', 'value' => WithdrawalRequest::where('currency', 'USD')->where('status', 'approved')
                ->whereDate('created_at', today())->sum('amount'), 'prefix' => '$'],
            ['label' => 'NGN Average', 'value' => round($ngnWithdrawals > 0 ? $ngnWithdrawals / WithdrawalRequest::where('currency', 'NGN')->where('status', 'approved')->count() : 0, 2), 'prefix' => '₦'],
            ['label' => 'USD Average', 'value' => round($usdWithdrawals > 0 ? $usdWithdrawals / WithdrawalRequest::where('currency', 'USD')->where('status', 'approved')->count() : 0, 2), 'prefix' => '$'],
        ];
    }

    public function list(array $filters = []): array
    {
        $query = WithdrawalRequest::with(['user:id,name,email', 'reviewer:id,name']);

        $builder = new ReportQueryBuilder($query);
        $builder->setAllowedSorts(['created_at', 'amount', 'status'])
            ->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->applyStatus($filters['status'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($w) => [
                'id' => $w->id,
                'user' => $w->user?->name ?? 'N/A',
                'amount' => $w->amount,
                'currency' => $w->currency ?? 'USD',
                'status' => $w->status,
                'method' => $w->bank_code ?? 'N/A',
                'account' => $w->account_number ?? 'N/A',
                'reviewed_by' => $w->reviewer?->name ?? null,
                'created_at' => $w->created_at?->format('Y-m-d H:i'),
                'updated_at' => $w->updated_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}