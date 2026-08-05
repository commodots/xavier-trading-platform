<?php

namespace App\Services\Reports;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class SubscriptionReportService extends BaseReportService
{
    use Concerns\InteractsWithCharts;

    /**
     * Main Report
     */
    public function generate(Request $request): array
    {
        $query = UserSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name,price']);

        $this->applyFilters($query, $request);

        $this->applySorting($query, $request);

        $table = $this->paginate($query, $request);

        return $this->response(
            $this->summary(),
            $table,
            [
                $this->monthlyChart(UserSubscription::class, 'created_at', 'id', 'count', 'Subscription Growth'),
                $this->statusChart(UserSubscription::class, 'status', 'Subscription Status'),
            ],
            $request->all()
        );
    }

    /**
     * Summary
     */
    public function summary(): array
    {
        $plans = SubscriptionPlan::all();
        $summary = [];

        foreach ($plans as $plan) {
            $summary[$plan->name] = UserSubscription::where('subscription_plan_id', $plan->id)
                ->where('status', 'active')->count();
        }

        $summary['trial'] = UserSubscription::where('status', 'trial')->count();
        $summary['expired'] = UserSubscription::where('status', 'expired')->count();
        $summary['cancelled'] = UserSubscription::where('status', 'cancelled')->count();
        $summary['renewals'] = UserSubscription::where('status', 'active')
            ->where('expires_at', '>', now())->count();
        $summary['revenue'] = UserSubscription::where('status', 'active')
            ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price');

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
            'data' => collect($paginator->items())->map(fn ($s) => [
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

    /**
     * Export
     */
    public function export(Request $request)
    {
        $query = UserSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name,price']);

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
            })
                ->orWhereHas('plan', function ($plan) use ($search) {
                    $plan->where('name', 'like', "%{$search}%");
                });
        });
    }
}
