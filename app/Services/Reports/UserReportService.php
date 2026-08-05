<?php

namespace App\Services\Reports;

use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Http\Request;

class UserReportService extends BaseReportService
{
    protected ReportQueryBuilder $queryBuilder;

    use Concerns\InteractsWithCharts;

    /**
     * Main Report
     */
    public function generate(Request $request): array
    {
        $query = User::query()
            ->with(['kyc'])
            ->withSum('wallets', 'balance');

        $this->applyFilters($query, $request);

        $this->applySorting($query, $request);

        $table = $this->paginate($query, $request);

        return $this->response(
            $this->summaryData(),
            $table,
            [
                $this->monthlyChart(User::class, 'created_at', 'id', 'count', 'User Growth'),
                $this->statusChart(User::class, 'kyc_status', 'KYC Status'),
            ],
            $request->all()
        );
    }

    /**
     * Summary for new generate() - associative array format.
     */
    public function summaryData(): array
    {
        return [
            'total_users' => User::count(),
            'verified' => User::where('kyc_status', 'verified')->count(),
            'active' => User::where('is_suspended', false)->whereNotNull('email_verified_at')->count(),
            'suspended' => User::where('is_suspended', true)->count(),
            'premium' => User::where('subscription_status', 'active')->count(),
            'trial' => User::where('subscription_status', 'trial')->count(),
            'expired' => User::where('subscription_status', 'expired')->count(),
            'pending_kyc' => KycProfile::where('status', 'pending')->count(),
            'pending' => KycProfile::where('status', 'pending')->count(),
        ];
    }

    /**
     * Summary for existing Users.vue page - array of StatCard objects.
     */
    public function summary(): array
    {
        return [
            ['title' => 'Total Users', 'value' => User::count(), 'icon' => 'users', 'color' => '#0047AB'],
            ['title' => 'New Today', 'value' => User::whereDate('created_at', today())->count(), 'icon' => 'user-plus', 'color' => '#10B981'],
            ['title' => 'Verified', 'value' => User::where('kyc_status', 'verified')->count(), 'icon' => 'check-circle', 'color' => '#10B981'],
            ['title' => 'Pending KYC', 'value' => KycProfile::where('status', 'pending')->count(), 'icon' => 'clock', 'color' => '#F59E0B'],
            ['title' => 'Suspended', 'value' => User::where('is_suspended', true)->count(), 'icon' => 'ban', 'color' => '#EF4444'],
            ['title' => 'Premium', 'value' => User::where('subscription_status', 'active')->count(), 'icon' => 'star', 'color' => '#8B5CF6'],
            ['title' => 'Inactive', 'value' => User::where('last_active_at', '<', now()->subDays(30))->count(), 'icon' => 'user-x', 'color' => '#6B7280'],
        ];
    }

    public function list(array $filters = []): array
    {
        $query = User::query()->with('kyc')->withSum('wallets', 'balance');

        $builder = new ReportQueryBuilder($query);
        $builder->setAllowedSorts(['name', 'email', 'created_at', 'kyc_status', 'last_active_at', 'subscription_status'])
            ->setDefaultSort('created_at', 'desc')
            ->applySearch($filters['search'] ?? null, ['name', 'email', 'phone'])
            ->applyDateRange($filters['from'] ?? null, $filters['to'] ?? null, 'created_at')
            ->applyStatus($filters['kyc_status'] ?? null, 'kyc_status');

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }
        if (! empty($filters['subscription'])) {
            $query->where('subscription_status', $filters['subscription']);
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'suspended') {
                $query->where('is_suspended', true);
            } elseif ($filters['status'] === 'active') {
                $query->where('is_suspended', false)->where('kyc_status', 'verified');
            } elseif ($filters['status'] === 'inactive') {
                $query->where('last_active_at', '<', now()->subDays(30));
            }
        }

        $perPage = (int) ($filters['per_page'] ?? 50);
        $paginator = $builder->paginate($perPage);

        $users = collect($paginator->items())->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'country' => $u->country ?? 'N/A',
            'wallet_balance' => $u->wallets_sum_balance ?? $u->wallet_balance ?? 0,
            'subscription_status' => $u->subscription_status ?? 'none',
            'kyc_status' => $u->kyc_status ?? ($u->kyc?->status ?? 'none'),
            'is_suspended' => $u->is_suspended,
            'status' => $u->is_suspended ? 'suspended' : ($u->kyc_status === 'verified' ? 'active' : 'pending'),
            'joined' => $u->created_at?->format('Y-m-d H:i'),
            'last_login' => $u->last_active_at?->format('Y-m-d H:i'),
            'avatar' => $u->avatar ?? null,
        ])->toArray();

        return [
            'data' => $users,
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function filters(): array
    {
        return [
            'countries' => User::select('country')->distinct()->whereNotNull('country')->orderBy('country')->pluck('country'),
            'subscriptions' => ['active', 'inactive', 'trial'],
            'roles' => ['user', 'super-admin', 'admin', 'staff'],
            'statuses' => ['active', 'suspended', 'inactive'],
            'kyc_statuses' => ['verified', 'pending', 'rejected', 'none'],
        ];
    }

    public function export(array $filters = []): array
    {
        $result = $this->list($filters + ['per_page' => 10000]);

        return $result['data'];
    }

    public function getUserById(int $id): ?array
    {
        $user = User::with('kyc')->find($id);
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country ?? 'N/A',
            'wallet_balance' => $user->wallet_balance ?? 0,
            'subscription_status' => $user->subscription_status ?? 'none',
            'kyc_status' => $user->kyc_status ?? ($user->kyc?->status ?? 'none'),
            'is_suspended' => $user->is_suspended,
            'status' => $user->is_suspended ? 'suspended' : ($user->kyc_status === 'verified' ? 'active' : 'pending'),
            'joined' => $user->created_at?->format('Y-m-d H:i'),
            'last_login' => $user->last_active_at?->format('Y-m-d H:i'),
            'avatar' => $user->avatar ?? null,
            'wallet_balance' => $user->wallets_sum_balance ?? $user->wallet_balance ?? 0,
        ];
    }

    /**
     * Search
     */
    protected function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
