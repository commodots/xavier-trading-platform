<?php

namespace App\Services\Reports;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Ledger;

class WalletReportService
{
    public function summary(): array
    {
        return [
            ['label' => 'Wallet Balance', 'value' => Wallet::sum('balance'), 'prefix' => '$'],
            ['label' => 'Locked Balance', 'value' => Wallet::sum('locked'), 'prefix' => '$'],
            ['label' => 'Total Wallets', 'value' => Wallet::count()],
        ];
    }

    public function ledger(array $filters = []): array
    {
        $query = WalletTransaction::query();
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($wt) => [
                'id' => $wt->id,
                'user_id' => $wt->user_id,
                'type' => $wt->type,
                'amount' => $wt->amount,
                'currency' => $wt->wallet_currency ?? 'N/A',
                'reference' => $wt->reference ?? 'N/A',
                'note' => $wt->note ?? '',
                'created_at' => $wt->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function adjustments(array $filters = []): array
    {
        $query = Ledger::query();
        $builder = new ReportQueryBuilder($query);
        $builder->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($l) => [
                'id' => $l->id,
                'user_id' => $l->user_id,
                'amount' => $l->amount,
                'type' => $l->type,
                'currency' => $l->currency,
                'reference' => $l->reference ?? 'N/A',
                'note' => $l->meta['note'] ?? '',
                'created_at' => $l->created_at?->format('Y-m-d H:i'),
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}