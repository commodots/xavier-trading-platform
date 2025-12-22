<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\UserKyc;

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
        $query = User::select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'status', 'created_at');

        if ($request->q) {
            $query->where(function ($q) use ($request) {
                $q->where('email', 'like', "%{$request->q}%")
                    ->orWhere('first_name', 'like', "%{$request->q}%")
                    ->orWhere('last_name', 'like', "%{$request->q}%");
            });
        }

        $users = $query->paginate(20);

        return response()->json([
            'success' => true,
            'users' => $users->items(),   // <-- clean list of users
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
        $user = User::with('kyc')->findOrFail($id);

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

        $user->status = $user->status === 'active' ? 'disabled' : 'active';
        $user->save();

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
        $request->validate([
            'role' => 'required|in:admin,user,accounts,custom'
        ]);

        $user = User::findOrFail($id);
        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'role' => $user->role
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | LIST TRANSACTIONS
    |--------------------------------------------------------------------------
    */
    public function transactions(Request $request)
    {
        $transactions = Transaction::latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions
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
        $kyc->status = $request->status;
        $kyc->save();

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
        $order = Order::create([
            'user_id' => auth()->id(),
            'symbol' => $request->symbol,
            'side' => $request->side,
            'type' => $request->type,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'status' => 'open',
            'source' => 'web',
        ]);

        ExecutionRouter::route($order);

        return response()->json($order);
    }
    public function platformEarnings()
    {
        return Transaction::select('type')
            ->selectRaw('SUM(platform_fee) as total_earnings')
            ->groupBy('type')
            ->get();
    }
}
