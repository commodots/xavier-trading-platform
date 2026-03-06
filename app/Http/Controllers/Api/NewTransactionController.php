<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use App\Models\Wallet;
use App\Models\LinkedAccount;
use Illuminate\Http\Request;
use App\Models\TransactionCharge;
use App\Models\ActivityLog;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoWallet;

class NewTransactionController extends Controller
{
    private function resolveModels($user, Request $request = null)
{
    // Prioritize the query parameter, fallback to the user's saved mode
    $mode = $request ? $request->query('mode', $user->trading_mode) : $user->trading_mode;
    $isDemo = $mode === 'demo';
    
    return (object) [
        'isDemo' => $isDemo,
        'wallet' => $isDemo ? new DemoWallet() : new Wallet(),
        'transaction' => $isDemo ? new DemoTransaction() : new NewTransaction(),
    ];
}

    public function index(Request $request) {
    $models = $this->resolveModels(auth()->user(), $request);
    return response()->json(
        $models->transaction->where('user_id', auth()->id())->latest()->limit(10)->get()
    );
}

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:NGN,USD'
        ]);

        $user = auth()->user();
        $models = $this->resolveModels($user);

        Log::info('Deposit initiated for user ' . $user->id . ' with amount ' . $request->amount);

        return DB::transaction(function () use ($user, $request, $models) {
            $chargeConfig = TransactionCharge::where('transaction_type', 'deposit')->where('active', true)->first();
            $charge = 0;
            if ($chargeConfig) {
                $charge = $chargeConfig->charge_type === 'percentage' 
                    ? ($request->amount * $chargeConfig->value / 100)
                    : $chargeConfig->value;
            }
            $netAmount = $request->amount - $charge;

            if ($netAmount <= 0) {
                return response()->json(['success' => false, 'message' => 'Deposit amount must be greater than the transaction charge.'], 422);
            }

            $transaction = $models->transaction->create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => 'completed',
                'net_amount' => $netAmount
            ]);

             Log::info('Transaction created with ID ' . $transaction->id);
            $wallet = $models->wallet->firstOrCreate(
                ['user_id' => $user->id, 'currency' => $request->currency]
            );

            $clearedCol = $request->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $wallet->increment($clearedCol, $netAmount);
            $wallet->increment('balance', $netAmount);

            Log::info('Wallet balance incremented by ' . $netAmount . ' for user ' . $user->id);

            try {
            ActivityLog::create([
                'user_id'    => $user->id,
                'activity'   => 'Deposit',
                'details'    => "Deposited {$request->amount} {$request->currency}. Net added to wallet: {$netAmount} after fees.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }
            return response()->json([
                'success' => true,
                'details' => $transaction->fresh()
            ]);
        });
    }

    public function withdraw(Request $request)
    {

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:NGN,USD',
            'linked_account_id' => 'required|exists:linked_accounts,id'
        ]);

        $user = auth()->user();
        $kyc = $user->kyc;
        $models = $this->resolveModels($user);

        //KYC Guard (Only enforced for LIVE mode!)
        if (!$models->isDemo) {
            if (!$kyc || $kyc->status !== 'verified' || $kyc->tier < 1) {
                return response()->json(['success' => false, 'message' => 'Verified KYC is required for live withdrawals.'], 403);
            }
        }

        $tierSettings = \App\Models\KycSetting::where('tier', $kyc->tier ?? 1)->first();
        $dailyLimit = $tierSettings ? $tierSettings->daily_limit : 0;

        $todayWithdrawn = $models->transaction->where('user_id', $user->id)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['completed', 'pending'])
            ->whereDate('created_at', now())
            ->sum('amount');

        if (!$models->isDemo && ($todayWithdrawn + $request->amount) > $dailyLimit) {
            $remaining = max(0, $dailyLimit - $todayWithdrawn);
            return response()->json(['success' => false, 'message' => "Daily limit exceeded. Remaining: " . number_format($remaining, 2)], 403);
        }

        $account = LinkedAccount::where('user_id', $user->id)->where('id', $request->linked_account_id)->firstOrFail();

        if ($account->type === 'bank' && ($account->currency ?? '') !== $request->currency) {
            return response()->json(['success' => false, 'message' => 'Selected bank account currency does not match withdrawal currency.'], 422);
        }

        $chargeConfig = TransactionCharge::where('transaction_type', 'withdrawal')->where('active', true)->first();
        $chargeAmount = 0;
        if ($chargeConfig) {
            $chargeAmount = $chargeConfig->charge_type === 'percentage' 
                ? ($request->amount * $chargeConfig->value / 100)
                : $chargeConfig->value;
        }
        $totalDeduction = $request->amount + $chargeAmount;

        try {
            return DB::transaction(function () use ($user, $request, $totalDeduction, $account, $chargeAmount, $models) {
                
                $wallet = $models->wallet->where('user_id', $user->id)
                    ->where('currency', $request->currency)
                    ->lockForUpdate()
                    ->first();

                $clearedCol = $request->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
                $available = $wallet ? $wallet->{$clearedCol} : 0;

                if (!$wallet || $available < $totalDeduction) {
                    throw new \Exception("Insufficient cleared {$request->currency} balance");
                }

                $txn = $models->transaction->create([
                    'user_id' => $user->id,
                    'type' => 'withdrawal',
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'charge' => $chargeAmount,
                    'net_amount' => $totalDeduction,
                    'status' => $models->isDemo ? 'completed' : 'pending', // Demo finishes instantly
                    'meta' => [
                        'bank' => $account->provider,
                        'acc_no' => $account->account_number,
                        'acc_name' => $account->account_name,
                        'mode' => $user->trading_mode
                    ]
                ]);

                $wallet->decrement($clearedCol, $totalDeduction);
                $wallet->decrement('balance', $totalDeduction);

                try {
                    ActivityLog::create([
                        'user_id'    => $user->id,
                        'activity'   => 'Withdrawal',
                        'details'    => "Withdrew {$request->amount} {$request->currency}. Total deduction: {$totalDeduction}.",
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                } catch (\Throwable $e) {}

                return response()->json(['success' => true, 'details' => $txn->fresh()]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    public function show($id)
{
    $models = $this->resolveModels(auth()->user());
    
    // Find the transaction owned by this user
    $transaction = $models->transaction
        ->where('user_id', auth()->id())
        ->where('id', $id)
        ->first();

    if (!$transaction) {
        return response()->json(['message' => 'Transaction not found'], 404);
    }

    return response()->json(['success' => true, 'data' => $transaction]);
}
}