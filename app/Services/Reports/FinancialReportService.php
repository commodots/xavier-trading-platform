<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Fee;
use App\Models\NewTransaction;
use App\Models\PlatformEarning;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function summary(): array
    {
        $ngnBalance = Wallet::where('currency', 'NGN')->sum('ngn_cleared');
        $usdBalance = Wallet::where('currency', 'USD')->sum('usd_cleared');

        $totalDeposits = NewTransaction::query()->where('type', 'deposit');
        $totalNgnDeposits = (clone $totalDeposits)->where('currency', 'NGN')->sum('amount');
        $totalUsdDeposits = (clone $totalDeposits)->where('currency', 'USD')->sum('amount');

        $totalWithdrawals = WithdrawalRequest::query();
        $totalNgnWithdrawals = (clone $totalWithdrawals)->where('currency', 'NGN')->sum('amount');
        $totalUsdWithdrawals = (clone $totalWithdrawals)->where('currency', 'USD')->sum('amount');

        $pendingWithdrawals = WithdrawalRequest::query()->where('status', 'pending');
        $pendingNgnWithdrawals = (clone $pendingWithdrawals)->where('currency', 'NGN')->sum('amount');
        $pendingUsdWithdrawals = (clone $pendingWithdrawals)->where('currency', 'USD')->sum('amount');

        return [
            ['title' => 'Total NGN Deposits', 'value' => $totalNgnDeposits, 'icon' => 'trending-up', 'color' => '#10B981', 'prefix' => '₦'],
            ['title' => 'Total USD Deposits', 'value' => $totalUsdDeposits, 'icon' => 'trending-up', 'color' => '#10B981', 'prefix' => '$'],
            ['title' => 'Total NGN Withdrawals', 'value' => $totalNgnWithdrawals, 'icon' => 'trending-down', 'color' => '#EF4444', 'prefix' => '₦'],
            ['title' => 'Total USD Withdrawals', 'value' => $totalUsdWithdrawals, 'icon' => 'trending-down', 'color' => '#EF4444', 'prefix' => '$'],
            ['title' => 'Pending NGN Withdrawals', 'value' => $pendingNgnWithdrawals, 'icon' => 'clock', 'color' => '#F59E0B', 'prefix' => '₦'],
            ['title' => 'Pending USD Withdrawals', 'value' => $pendingUsdWithdrawals, 'icon' => 'clock', 'color' => '#F59E0B', 'prefix' => '$'],
            ['title' => 'NGN Balance', 'value' => $ngnBalance, 'icon' => 'dollar', 'color' => '#0047AB', 'prefix' => '₦'],
            ['title' => 'USD Balance', 'value' => $usdBalance, 'icon' => 'dollar', 'color' => '#10B981', 'prefix' => '$'],
            ['title' => 'Fees', 'value' => Fee::query()->sum('amount'), 'icon' => 'receipt', 'color' => '#8B5CF6', 'prefix' => '$'],
            ['title' => 'Revenue', 'value' => PlatformEarning::query()->sum('amount'), 'icon' => 'activity', 'color' => '#F59E0B', 'prefix' => '$'],
            ['title' => 'Commissions', 'value' => NewTransaction::query()->where('type', 'commission')->sum('amount'), 'icon' => 'shield', 'color' => '#10B981', 'prefix' => '$'],
        ];
    }

    public function getStatistics(string $type = 'deposits'): array
    {
        $query = $this->queryForType($type);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'today' => $query->whereDate('created_at', $today)->sum('amount'),
            'yesterday' => $query->whereDate('created_at', $yesterday)->sum('amount'),
            'this_week' => $query->where('created_at', '>=', $thisWeek)->sum('amount'),
            'this_month' => $query->where('created_at', '>=', $thisMonth)->sum('amount'),
        ];
    }

    public function statistics(array $filters = []): array
    {
        $type = $filters['type'] ?? 'deposits';
        $query = $this->queryForType($type);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'today' => $query->whereDate('created_at', $today)->sum('amount'),
            'yesterday' => $query->whereDate('created_at', $yesterday)->sum('amount'),
            'this_week' => $query->where('created_at', '>=', $thisWeek)->sum('amount'),
            'this_month' => $query->where('created_at', '>=', $thisMonth)->sum('amount'),
        ];
    }

    public function deposits(array $filters = []): array
    {
        $query = NewTransaction::query()->with('user:id,name,email')->where('type', 'deposit');

        return $this->paginateTransactionQuery($query, $filters);
    }

    protected function queryForType(string $type)
    {
        return match ($type) {
            'withdrawals' => WithdrawalRequest::query(),
            'fees' => Fee::query(),
            'revenue' => PlatformEarning::query(),
            default => NewTransaction::query()->where('type', 'deposit'),
        };
    }

    public function withdrawals(array $filters = []): array
    {
        $query = WithdrawalRequest::with('user:id,name,email');

        return $this->paginateWithdrawalQuery($query, $filters);
    }

    public function walletTransactions(array $filters = []): array
    {
        $depositBuilder = new ReportQueryBuilder(
            NewTransaction::query()->with('user:id,name,email')->where('type', 'deposit')
        );
        $withdrawalBuilder = new ReportQueryBuilder(
            WithdrawalRequest::query()->with('user:id,name,email')
        );

        foreach ([$depositBuilder, $withdrawalBuilder] as $builder) {
            $builder->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null)
                ->applyStatus($filters['status'] ?? null);
        }

        $deposits = $depositBuilder->get()->map(fn ($transaction) => [
            'id' => 'deposit-'.$transaction->id,
            'user' => $transaction->user?->name ?? 'N/A',
            'reference' => $transaction->reference ?? 'DEP-'.$transaction->id,
            'type' => 'deposit',
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency ?? 'USD',
            'method' => $transaction->asset ?? 'Bank Transfer',
            'status' => $transaction->status ?? 'completed',
            'created_at' => $transaction->created_at?->format('Y-m-d H:i'),
        ])->values();

        $withdrawals = $withdrawalBuilder->get()->map(fn ($withdrawal) => [
            'id' => 'withdrawal-'.$withdrawal->id,
            'user' => $withdrawal->user?->name ?? 'N/A',
            'reference' => 'WD-'.$withdrawal->id,
            'type' => 'withdrawal',
            'amount' => (float) $withdrawal->amount,
            'currency' => $withdrawal->currency ?? 'NGN',
            'method' => $withdrawal->currency ?? 'Bank Transfer',
            'status' => $withdrawal->status ?? 'pending',
            'created_at' => $withdrawal->created_at?->format('Y-m-d H:i'),
        ])->values();

        $rows = $deposits->concat($withdrawals)
            ->sortByDesc('created_at')
            ->values();

        $perPage = (int) ($filters['per_page'] ?? 50);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data' => $items->toArray(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function fees(array $filters = []): array
    {
        $query = Fee::with(['user:id,name,email', 'trade:id,reference']);
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn ($f) => [
                'id' => $f->id,
                'user' => $f->user?->name ?? 'N/A',
                'reference' => $f->trade?->reference ?? 'FEE-'.$f->id,
                'amount' => $f->amount,
                'currency' => 'USD',
                'method' => $f->type,
                'status' => 'N/A',
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
            'data' => collect($paginator->items())->map(fn ($r) => [
                'id' => $r->id,
                'source' => $r->source,
                'type' => $r->transaction?->type ?? 'N/A',
                'reference' => $r->transaction?->reference ?? 'REV-'.$r->id,
                'user' => $r->transaction?->user?->name ?? 'N/A',
                'amount' => $r->amount,
                'currency' => $r->currency ?? 'USD',
                'method' => $r->source,
                'status' => $r->transaction?->status ?? 'N/A',
                'created_at' => $r->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * Server-side chart aggregation.
     *
     * Computes activity trend + status mix at database level over the FULL
     * filtered dataset (not just the current page), so charts are never empty.
     */
    public function charts(string $tab, array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $status = $filters['status'] ?? null;

        // Activity trend (daily buckets)
        $trend = $this->chartQueryForTab($tab, $filters)
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(COALESCE(amount, 0)) as total')
            )
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Status mix (record counts)
        $mix = $this->chartQueryForTab($tab, $filters)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->groupBy('status')
            ->orderBy('total', 'desc')
            ->get();

        return [
            'trend' => [
                'categories' => $trend->pluck('day')->map(fn ($d) => (string) substr((string) $d, 0, 10))->values()->toArray(),
                'series' => [[
                    'name' => ucwords(str_replace('_', ' ', $tab)),
                    'data' => $trend->pluck('total')->map(fn ($v) => (float) $v)->values()->toArray(),
                ]],
            ],
            'status' => [
                'categories' => $mix->pluck('status')->map(fn ($s) => $s ?: 'unknown')->values()->toArray(),
                'series' => [[
                    'name' => 'Records',
                    'data' => $mix->pluck('total')->map(fn ($v) => (int) $v)->values()->toArray(),
                ]],
            ],
        ];
    }

    protected function chartQueryForTab(string $tab, array $filters = [])
    {
        return match ($tab) {
            'withdrawals' => WithdrawalRequest::query(),
            'fees' => Fee::query(),
            'revenue' => PlatformEarning::query(),
            'expenses' => Expense::query()->where('status', '!=', 'cancelled'),
            default => NewTransaction::query()->where('type', 'deposit'),
        };
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
            'data' => collect($paginator->items())->map(fn ($t) => [
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
            'data' => collect($paginator->items())->map(fn ($w) => [
                'id' => $w->id,
                'user' => $w->user?->name ?? 'N/A',
                'reference' => 'WD-'.$w->id,
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
            'data' => collect($paginator->items())->map(fn ($wt) => [
                'id' => $wt->id,
                'user' => $wt->user?->name ?? 'N/A',
                'reference' => $wt->reference ?? 'N/A',
                'type' => $wt->type,
                'amount' => $wt->amount,
                'currency' => $wt->wallet_currency ?? 'N/A',
                'method' => $wt->type,
                'status' => 'N/A',
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
