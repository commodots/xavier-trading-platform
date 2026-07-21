<?php

namespace App\Services\Reports;

use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;

class SubscriptionReportService
{
    public function summary(): array
    {
        $plans = SubscriptionPlan::all();
        $summary = [];

        foreach ($plans as $plan) {
            $summary[] = [
                'label' => $plan->name,
                'value' => UserSubscription::where('subscription_plan_id', $plan->id)
                    ->where('status', 'active')->count(),
            ];
        }

        $summary[] = ['label' => 'Expired', 'value' => UserSubscription::where('status', 'expired')->count()];
        $summary[] = ['label' => 'Renewals', 'value' => UserSubscription::where('status', 'active')
            ->where('expires_at', '>', now())->count()];

        return $summary;
    }

    public function list(array $filters = []): array
    {
        $query = UserSubscription::with(['user:id,name,email', 'plan:id,name,price']);

        $builder = new ReportQueryBuilder($query);
        $builder->setAllowedSorts(['created_at', 'starts_at', 'expires_at', 'status'])
            ->setDefaultSort('created_at', 'desc')
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->applyStatus($filters['status'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn($s) => [
                'id' => $s->id,
                'user' => $s->user?->name ?? 'N/A',
                'plan' => $s->plan?->name ?? 'N/A',
                'started' => $s->starts_at?->format('Y-m-d'),
                'expires' => $s->expires_at?->format('Y-m-d'),
                'auto_renew' => $s->status === 'active',
                'status' => $s->status,
            ])->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}