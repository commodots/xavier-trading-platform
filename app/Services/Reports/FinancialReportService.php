<?php

namespace App\Services\Reports;

use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Models\WalletTransaction;
use App\Models\Fee;
use App\Models\PlatformEarning;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function summary(): array
    {
        return [
            ['label' => 'Total Deposits', 'value' => Transaction::where('type', 'deposit')->sum('amount'), 'prefix' => '$'],
            ['label' => 'Total Withdrawals', 'value' => WithdrawalRequest::where('status', 'approved')->sum('amount'), 'prefix' => '$'],
            ['label' => 'Pending Withdrawals', 'value' => WithdrawalRequest::where('status', 'pending')->sum('amount'), 'prefix' => '$'],
            ['label' => 'Wallet Balance', 'value' => \App\Models\Wallet::sum('balance'), 'prefix' => '$'],
            ['label' => 'Fees', 'value' => Fee::sum('amount'), 'prefix' => '$'],
            ['label' => 'Revenue', 'value' => PlatformEarning::sum('amount'), 'prefix' => '$'],
        ];
    }

    public function deposits(array $filters = []): array
    {
        $query = Transaction::with('user:id,name,email')->where('type', 'deposit');
        return $this->paginateTransactionQuery($query, $filters);
    }

    public function withdrawals(array $filters = []): array
    {
        $query = WithdrawalRequest::with('user:id,name,email');
        return $this->paginateWithdrawalQuery($query, $filters);
    }

    public function walletTransactions(array $filters = []): array
    {
        $query = WalletTransaction::with('user:id,name,email');
        return $this->paginateWalletQuery($query, $filters);
    }

    public function fees(array $filters = []): array
    {
        $query = Fee::with('user:id,name,email');
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($f) => [
                'id' => $f->id,
                'user' => $f->user?->name ?? 'N/A',
                'amount' => $f->amount,
                'currency' => 'USD',
                'type' => $f->type,
                'created_at' => $f->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function revenue(array $filters = []): array
    {
        $query = PlatformEarning::with('transaction.user:id,name,email');
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($r) => [
                'id' => $r->id,
                'source' => $r->source,
                'user' => $r->transaction?->user?->name ?? 'N/A',
                'amount' => $r->amount,
                'currency' => $r->currency,
                'created_at' => $r->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    protected function paginateTransactionQuery($query, array $filters): array
    {
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->applyStatus($filters['status'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($t) => [
                'id' => $t->id,
                'user' => $t->user?->name ?? 'N/A',
                'reference' => $t->reference ?? 'N/A',
                'amount' => $t->amount,
                'currency' => $t->currency ?? 'USD',
                'method' => $t->asset ?? 'N/A',
                'status' => $t->status,
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    protected function paginateWithdrawalQuery($query, array $filters): array
    {
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->applyStatus($filters['status'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($w) => [
                'id' => $w->id,
                'user' => $w->user?->name ?? 'N/A',
                'reference' => 'WD-' . $w->id,
                'amount' => $w->amount,
                'currency' => $w->currency ?? 'NGN',
                'method' => $w->currency ?? 'N/A',
                'status' => $w->status,
                'created_at' => $w->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    protected function paginateWalletQuery($query, array $filters): array
    {
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($wt) => [
                'id' => $wt->id,
                'user' => $wt->user?->name ?? 'N/A',
                'type' => $wt->type,
                'amount' => $wt->amount,
                'currency' => $wt->wallet_currency ?? 'N/A',
                'note' => $wt->note ?? '',
                'created_at' => $wt->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}