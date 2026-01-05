<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\UserKyc;
use App\Models\NewTransaction;
use App\Models\PlatformEarning;
use App\Models\TransactionCharge;
use App\Models\Order;
use App\Models\ActivityLog;

class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DASHBOARD SUMMARY
    |--------------------------------------------------------------------------
    */
    public function dashboard()
    {
        return response()->json([
            'success' => true,
            'stats' => [
                'total_users'        => User::count(),
                'pending_kyc'        => UserKyc::where('status', 'pending')->count(),
                'total_transactions' => Transaction::count(),
                'total_fees' => PlatformEarning::sum('amount'),
                'wallet_sums' => [
                    'ngn' => Wallet::where('currency', 'NGN')->sum('balance'),
                    'usd' => Wallet::where('currency', 'USD')->sum('balance'),
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
        $query = User::with('roles')->select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'status', 'created_at');

        if ($request->q) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', "%{$request->q}%")
                    ->orWhere('first_name', 'like', "%{$request->q}%")
                    ->orWhere('last_name', 'like', "%{$request->q}%");
            });
        }

        $users = $query->paginate(30);

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

        $walletNGN = Wallet::where('user_id', $id)->where('currency', 'NGN')->value('balance') ?? 0;
        $walletUSD = Wallet::where('user_id', $id)->where('currency', 'USD')->value('balance') ?? 0;

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
        $kycs = UserKyc::with('user')->latest()->paginate(20);

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
            'status' => 'required|in:pending,verified,rejected'
        ]);

        $kyc = UserKyc::findOrFail($id);
        $oldStatus = $kyc->status;
        $kyc->status = $request->status;
        $kyc->save();

        try {
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'activity'   => 'KYC Review',
                'details'    => "Reviewed KYC for {$kyc->user->email}. Status updated from [{$oldStatus}] to [{$request->status}]",
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
            'total_transactions' => Transaction::count(),
            'pending_kyc' => UserKyc::where('status', 'pending')->count(),
            'latest_transactions' => Transaction::latest()->take(5)->get(),
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
        return response()->json([
            'today_earnings' => PlatformEarning::whereDate('created_at', today())->sum('amount'),
            'this_month_earnings' => PlatformEarning::whereMonth('created_at', now()->month)->sum('amount'),
            'by_type' => NewTransaction::select('type')
                ->selectRaw('SUM(charge) as total_earnings')
                ->groupBy('type')->get(),
        ]);
    }
    public function exportTransactions(Request $request)
    {
        $query = Transaction::with('user');


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
        return TransactionCharge::all();
    }
    public function updateCharge(Request $request, $id)
    {
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
