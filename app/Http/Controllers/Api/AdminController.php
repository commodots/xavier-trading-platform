<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\UserKyc;
use App\Models\NewTransaction;
use App\Models\PlatformEarning;
use App\Models\TransactionCharge;
use App\Models\Order;
use App\Models\ActivityLog;
use App\Models\KycProfile;
use App\Models\KycSetting;
use App\Models\StaffPermission;
use App\Models\Ledger;
use App\Models\FxRate;

class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DASHBOARD SUMMARY
    |--------------------------------------------------------------------------
    */
    public function dashboard()
    {
        $ngnTotal = Wallet::where('currency', 'NGN')
            ->selectRaw('SUM(ngn_cleared + ngn_uncleared) as total')
            ->value('total') ?? 0;

        // Calculate USD Total (Cleared + Uncleared)
        $usdTotal = Wallet::where('currency', 'USD')
            ->selectRaw('SUM(usd_cleared + usd_uncleared) as total')
            ->value('total') ?? 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'users_count'        => User::count(),
                'pending_kyc'        => KycProfile::where('status', 'pending')->count(),
                'total_transactions' => NewTransaction::count(),
                'pending_orders'     => Order::where('status', 'pending')->count(),
                'wallets' => [
                    'ngn' => (float) $ngnTotal,
                    'usd' => (float) $usdTotal,
                ]
            ],
            'chart' => [
                'users' => [
                    'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    'data' => [5, 10, 15, 25, 40, 70, 100]
                ],
                'transactions' => [
                    'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    'data' => [10000, 20000, 15000, 50000, 35000, 45000, 60000]
                ]
            ]
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | USERS LIST
    |--------------------------------------------------------------------------
    */
    public function users(Request $request)
    {
        $query = User::with('roles')->select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'status', 'kyc_status', 'created_at');

        if ($request->q) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', "%{$request->q}%")
                    ->orWhere('first_name', 'like', "%{$request->q}%")
                    ->orWhere('last_name', 'like', "%{$request->q}%");
            });
        }

        $users = $query->latest()->paginate(30);

        $items = collect($users->items())->map(function ($u) {
            return [
                'id' => $u->id,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                'roles' => $u->getRoleNames(),
                'status' => $u->status,
                'kyc_status' => $u->kyc_status,
                'created_at' => $u->created_at,
            ];
        })->all();

        return response()->json([
            'success' => true,
            'users' => $items,
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]
        ]);
    }



    /*
    |--------------------------------------------------------------------------
    | SINGLE USER DETAIL PAGE
    |--------------------------------------------------------------------------
    */
    public function userDetail($id)
    {
        $user = User::with(['kyc', 'roles'])->findOrFail($id);

        $walletNGN = Wallet::where('user_id', $id)->where('currency', 'NGN')
    ->selectRaw('(ngn_cleared + ngn_uncleared) as total')->value('total') ?? 0;
 
$walletUSD = Wallet::where('user_id', $id)->where('currency', 'USD')
    ->selectRaw('(usd_cleared + usd_uncleared) as total')->value('total') ?? 0;

        $transactions = Transaction::where('user_id', $id)
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'user' => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'role'       => $user->role,
                'roles'      => $user->getRoleNames(),
                'status'     => $user->status,
                'created_at' => $user->created_at,
                'kyc'        => $user->kyc
            ],
            'wallet' => [
                'ngn' => $walletNGN,
                'usd' => $walletUSD,
            ],
            'transactions' => $transactions
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE USER STATUS (active/disabled)
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        $oldStatus = $user->status;

        $user->status = $user->status === 'active' ? 'disabled' : 'active';
        $user->save();


        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Toggle Status',
                'details'    => "Changed status for {$user->email} from [{$oldStatus}] to [{$user->status}]",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'status' => $user->status
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE USER ROLE
    |--------------------------------------------------------------------------
    */
    public function updateUserRole(Request $request, $id)
    {
        $validated = $request->validate([
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
            'role' => 'sometimes|string|exists:roles,name'
        ]);
        $user = User::findOrFail($id);
        $admin = auth()->user();

        $newRoles = $validated['roles'] ?? (isset($validated['role']) ? [$validated['role']] : []);

        if (empty($newRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide `role` or `roles` to update.'
            ], 422);
        }

        if (!in_array('user', $newRoles)) {
            $newRoles[] = 'user';
        }

        $oldRoles = $user->getRoleNames()->implode(', ');


        $user->syncRoles($newRoles);


        $user->role = in_array('admin', $newRoles) ? 'admin' : ($newRoles[0] ?? $user->role);
        $user->save();

        $roleString = implode(', ', $newRoles);

        ActivityLog::create([
            'user_id'    => $admin->id,
            'activity'   => 'Role Update',
            'details' => "Updated roles for {$user->email}. Changed from [{$oldRoles}] to [{$roleString}]",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role(s) updated successfully',
            'roles' => $user->getRoleNames(),
            'role' => in_array('admin', $newRoles) ? 'admin' : $newRoles[0]
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | LIST ORDERS
    |--------------------------------------------------------------------------
    */
    public function orders(Request $request)
    {
        $query = Order::with('user:id,first_name,last_name,email');

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('symbol', 'like', "%{$request->q}%")
                    ->orWhereHas('user', function ($userQuery) use ($request) {
                        $userQuery->where('email', 'like', "%{$request->q}%");
                    });
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'pending') {
                $query->whereIn('status', ['open', 'partially_filled']);
            } elseif ($request->status === 'successful') {
                $query->where('status', 'filled');
            } elseif ($request->status === 'failed') {
                $query->where('status', 'canceled');
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('side')) {
            $query->where('side', $request->side);
        }

        if ($request->filled('market')) {
            $query->where('market', $request->market);
        }

        $orders = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LIST TRANSACTIONS
    |--------------------------------------------------------------------------
    */
    public function transactions(Request $request)
    {
        $query = NewTransaction::with('user');


        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('id', 'like', "%{$request->q}%")
                    ->orWhereHas('user', function ($userQuery) use ($request) {
                        $userQuery->where('email', 'like', "%{$request->q}%");
                    });
            });
        }


        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }


        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $txns = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $txns,
            'total_fees_earned' => PlatformEarning::sum('amount')
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | LIST KYC RECORDS
    |--------------------------------------------------------------------------
    */
    public function kycs(Request $request)
    {
        $kycs = KycProfile::with('user')->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $kycs
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | REVIEW / UPDATE KYC STATUS
    |--------------------------------------------------------------------------
    */
    public function reviewKyc(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,verified,rejected',
            'tier' => 'sometimes|integer|min:0|max:3',
            'rejection_reason' => 'required_if:status,rejected',
            'daily_limit' => 'required|numeric|min:0'
        ]);

        $kyc = KycProfile::where('user_id', $id)->firstOrFail();


        $targetTier = $request->input('tier');
        if ($request->status === 'verified' && !$targetTier) {
            $targetTier = $this->computeTierFromDocuments($kyc);
        }

        $kyc->update([
            'status' => $request->status,
            'tier' => $targetTier ?? 0,
            'daily_limit' => $request->daily_limit,
            'level' => match ((int)($targetTier ?? 0)) {
                1 => 'basic',
                2 => 'mid',
                3 => 'full',
                default => 'none'
            },
            'rejection_reason' => $request->status === 'rejected' ? $request->rejection_reason : null
        ]);

        $user = $kyc->user;
        $user->kyc_status = $request->status;
        $user->save();
        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'KYC Review',
                'details'    => "Reviewed KYC for {$kyc->user->email}. Upgraded {$kyc->user->email} to Tier {$request->tier} with limit {$request->daily_limit}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC status updated',
            'kyc' => $kyc
        ]);
    }

    /**
     * Determine best tier for a given KYC profile by checking required_documents in kyc_settings
     */
    protected function computeTierFromDocuments($kyc)
    {
        $settings = \DB::table('kyc_settings')->orderByDesc('tier')->get();
        foreach ($settings as $s) {
            $req = json_decode($s->required_documents ?? '[]', true) ?: [];
            if (empty($req)) continue;

            $hasAll = true;
            foreach ($req as $doc) {
                // map document keys to kyc columns
                $col = match ($doc) {
                    'bvn' => 'bvn',
                    'nin' => 'nin',
                    'tin' => 'tin',
                    'intl_passport' => 'intl_passport',
                    'national_id' => 'national_id',
                    'drivers_license' => 'drivers_license',
                    'proof_of_address' => 'proof_of_address',
                    default => $doc
                };

                if (empty($kyc->{$col})) {
                    $hasAll = false;
                    break;
                }
            }

            if ($hasAll) {
                return (int)$s->tier;
            }
        }

        return 0;
    }

    public function getKycSettings()
    {
        $settings = KycSetting::orderBy('tier')->get();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function getStaffPermissions()
    {
        // List all roles except 'admin' and 'user' and current permission mappings
        $roles = \Spatie\Permission\Models\Role::whereNotIn('name', ['admin', 'user'])->pluck('name');
        $mappings = [];
        foreach ($roles as $r) {
            $sp = StaffPermission::forRole($r);
            $mappings[] = [
                'role' => $r,
                'permissions' => $sp ? $sp->permissions : []
            ];
        }

        return response()->json(['success' => true, 'data' => $mappings]);
    }

    public function updateStaffPermissions(Request $request)
    {
        $request->validate([
            'role' => 'required|string',
            'permissions' => 'required|array'
        ]);

        // ensure only admin can change permissions
        $user = auth()->user();
        $isAdmin = (isset($user->role) && strtolower($user->role) === 'admin') || $user->hasRole('admin');

        if (!$isAdmin) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Only admins can manage staff access.'], 403);
        }

        StaffPermission::updateOrCreate([
            'role' => $request->role
        ], [
            'permissions' => $request->permissions
        ]);

        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'activity' => 'Staff Permission Update',
                'details' => 'Updated staff role permissions for ' . $request->role,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json(['success' => true, 'message' => 'Permissions updated']);
    }

    /**
     * Return a single KYC profile for review by admins.
     */
    public function getKyc($id)
    {
        $kyc = KycProfile::with('user')->where('user_id', $id)->firstOrFail();


        // Provide friendly fields expected by the frontend if they exist
        return response()->json([
            ...$kyc->toArray(),
            'id_card' => $kyc->id_card_front ? asset('storage/' . $kyc->id_card_front) : asset('storage/' . $kyc->id_card),
            'selfie'  => $kyc->selfie ? asset('storage/' . $kyc->selfie) : null,
        ]);
    }
    public function updateKycSettings(Request $request)
    {
        $user = auth()->user();

        $isAdmin = (isset($user->role) && strtolower($user->role) === 'admin') || $user->hasRole('admin');

        if (!$isAdmin && !\App\Services\StaffPermissionService::roleHasCapability($user, 'manage_kyc_settings')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $request->validate([
            'settings' => 'required|array',
            'settings.*.tier' => 'required|integer',
            'settings.*.daily_limit' => 'required|numeric',
            'settings.*.required_documents' => 'sometimes|array'
        ]);

        foreach ($request->settings as $set) {
            KycSetting::updateOrCreate(
                ['tier' => $set['tier']],
                [
                    'tier_name' => $set['tier_name'] ?? 'Tier ' . $set['tier'],
                    'daily_limit' => $set['daily_limit'],
                    'required_documents' => $set['required_documents'] ?? []
                ]
            );
        }
        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Update KYC Settings',
                'details'    => 'Admin updated global tier limits and document requirements.',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json(['success' => true, 'message' => 'Tier limits updated']);
    }


    public function destroyKycSetting($tier)
    {
        $user = auth()->user();
        $isAdmin = (isset($user->role) && strtolower($user->role) === 'admin') || $user->hasRole('admin');

        if (!$isAdmin && !\App\Services\StaffPermissionService::roleHasCapability($user, 'manage_kyc_settings')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $setting = KycSetting::where('tier', $tier)->first();

        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'Tier not found'], 404);
        }

        $setting->delete();

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Delete KYC Tier',
                'details'    => "Admin deleted KYC Tier {$tier}.",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json(['success' => true, 'message' => "Tier {$tier} deleted successfully"]);
    }

    /*
    |--------------------------------------------------------------------------
    | BASIC ADMIN STATS
    |--------------------------------------------------------------------------
    */
    public function stats()
    {
        return response()->json([
            'success' => true,
            'total_users' => User::count(),
            'total_transactions' => NewTransaction::count(),
            'pending_kyc' => KycProfile::where('status', 'pending')->count(),
            'latest_transactions' => NewTransaction::latest()->take(5)->get(),
            'user_growth' => [100, 150, 200, 260, 340, 500, 650]
        ]);
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:20',
            'side' => 'required|in:buy,sell',
            'type' => 'required|string|max:30',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0.00000001',
        ]);

        $order = Order::create([
            'user_id' => auth()->id(),
            'symbol' => $request->symbol,
            'side' => $request->side,
            'type' => $request->type,
            'price' => (float) $request->price,
            'quantity' => (float) $request->quantity,
            'status' => 'open',
            'source' => 'web',
        ]);

        ExecutionRouter::route($order);

        return response()->json($order);
    }
    public function getEarnings()
    {
        $baseRate = FxRate::latest()->value('base_rate') ?? 1500;

        $todayFxUsd = Ledger::where('is_platform', true)->where('type', 'FX_MARKUP_PROFIT')->whereDate('created_at', today())->sum('amount');
        $monthFxUsd = Ledger::where('is_platform', true)->where('type', 'FX_MARKUP_PROFIT')->whereMonth('created_at', now()->month)->sum('amount');

        $todayFxNgn = $todayFxUsd * $baseRate;
        $monthFxNgn = $monthFxUsd * $baseRate;

        $todayLegacy = PlatformEarning::whereDate('created_at', today())->sum('amount_ngn');
        $monthLegacy = PlatformEarning::whereMonth('created_at', now()->month)->sum('amount_ngn');

        $todayTxnFees = NewTransaction::whereDate('created_at', today())->sum('charge') ?? 0;
        $monthTxnFees = NewTransaction::whereMonth('created_at', now()->month)->sum('charge') ?? 0;

        return response()->json([
            'today_earnings' => $todayFxNgn + $todayLegacy + $todayTxnFees,
            'this_month_earnings' => $monthFxNgn + $monthLegacy + $monthTxnFees,
            'by_type' => [
                ['type' => 'FX Markup', 'total_earnings' => $monthFxNgn],
                ['type' => 'Legacy Earnings', 'total_earnings' => $monthLegacy],
                ['type' => 'Transaction Fees', 'total_earnings' => $monthTxnFees]
            ],
        ]);
    }

    /**
     * Earnings report with pagination, filtering and timeseries data
     */
    public function getEarningsReport(Request $request)
    {
        $query = PlatformEarning::with('transaction.user');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where('source', 'like', "%{$q}%")
                ->orWhereHas('transaction.user', function ($u) use ($q) {
                    $u->where('email', 'like', "%{$q}%");
                });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $earnings = $query->latest()->paginate($request->input('per_page', 20));
        // timeseries for chart: daily totals in range

        $totalNgValue = $query->sum('amount_ngn');

        $start = $request->filled('start_date') ? \Carbon\Carbon::parse($request->start_date) : now()->subDays(14);
        $end = $request->filled('end_date') ? \Carbon\Carbon::parse($request->end_date) : now();

        $series = PlatformEarning::selectRaw("DATE(created_at) as day, SUM(amount_ngn) as total")
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->groupBy('day')
            ->orderBy('day')
            ->get();


        return response()->json([
            'success' => true,
            'data' => $earnings,
            'timeseries' => $series,
            'total_ngn' => number_format($totalNgValue, 2)
        ]);
    }
    public function exportTransactions(Request $request)
    {
        $query = NewTransaction::with('user');


        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);

        $transactions = $query->latest()->get();

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'User', 'Type', 'Amount', 'Charge', 'Status', 'Date']);

            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->id,
                    $tx->user->email ?? 'N/A',
                    $tx->type,
                    $tx->amount,
                    $tx->charge,
                    $tx->status,
                    $tx->created_at
                ]);
            }
            fclose($file);
        };

        // Log export action
        try {
            ActivityLog::log(auth()->id(), 'Export Transactions', [
                'filters' => $request->only(['type', 'status'])
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }

        return response()->stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=transactions.csv",
        ]);
    }
    public function getCharges()
    {
        if (!auth()->user()->hasRole('admin') && !\App\Services\StaffPermissionService::roleHasCapability(auth()->user(), 'manage_transaction_charges')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return TransactionCharge::all();
    }
    public function updateCharge(Request $request, $id)
    {
        if (!auth()->user()->hasRole('admin') && !\App\Services\StaffPermissionService::roleHasCapability(auth()->user(), 'manage_transaction_charges')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $request->validate([
            'charge_type' => 'required|in:flat,percentage',
            'value' => 'required|numeric',
            'active' => 'required|boolean'
        ]);

        $charge = TransactionCharge::findOrFail($id);
        $oldVal = "{$charge->value} ({$charge->charge_type})";

        $charge->update($request->all());
        $newVal = "{$charge->value} ({$charge->charge_type})";

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'Charge Update',
                'details'    => "Updated {$charge->transaction_type} fee. Changed from {$oldVal} to {$newVal}. Active: " . ($charge->active ? 'Yes' : 'No'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json(['success' => true, 'message' => 'Charge updated']);
    }
    public function getActivityLogs(Request $request)
    {
        $query = ActivityLog::with('user:id,name,email');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($query) use ($q) {
                $query->where('activity', 'like', "%{$q}%")
                    ->orWhere('details', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        // Filter by Activity Type (e.g., Login, Logout, Profile Update)
        if ($request->filled('type')) {
            $query->where('activity', $request->type);
        }

        // Filter by Date Range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
    public function exportActivityLogs(Request $request)
    {
        $query = ActivityLog::with('user:id,name,email');


        if ($request->filled('type')) {
            $query->where('activity', $request->type);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->latest()->get();

        $csvFileName = 'activity_logs_' . now()->format('Y_m_d_His') . '.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['User', 'Email', 'Activity', 'IP Address', 'Date'];

        // Log export of activity logs
        try {
            ActivityLog::log(auth()->id(), 'Export Activity Logs', [
                'filters' => $request->only(['type', 'start_date', 'end_date'])
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        $callback = function () use ($logs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->user->name ?? 'Unknown',
                    $log->user->email ?? 'N/A',
                    $log->activity,
                    $log->ip_address,
                    $log->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
