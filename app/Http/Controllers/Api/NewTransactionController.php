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

class NewTransactionController extends Controller
{
    public function index()
    {
        $transactions = NewTransaction::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        return response()->json($transactions);
    }

    public function deposit(Request $request)
    {
        $status = TransactionType::where('name', 'deposit')->first();
        if ($status && !$status->active) {
            return response()->json([
                'success' => false,
                'message' => 'Deposits are temporarily disabled.'
            ], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:NGN,USD'
        ]);

        $user = auth()->user();
        $currency = $request->currency;

        Log::info('Deposit initiated for user ' . $user->id . ' with amount ' . $request->amount);

        // 1. Create transaction record
        $transaction = NewTransaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => 'completed',
            'net_amount' => $request->amount
        ]);

        Log::info('Transaction created with ID ' . $transaction->id);

        // 2. Calculate fee and update the record
        $charge = TransactionCharge::calculate('deposit', $request->amount, $transaction);
        $netAmount = $request->amount - $charge;

        $transaction->update(['net_amount' => $netAmount]);

        Log::info('Transaction updated with net_amount ' . $netAmount);

        // 3. Update the Wallet balance
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            ['balance' => 0, 'ngn_cleared' => 0, 'usd_cleared' => 0, 'locked' => 0]
        );

        $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
        
        $wallet->increment($clearedCol, $netAmount);
        $wallet->increment('balance', $netAmount);

        Log::info('Wallet balance incremented by ' . $netAmount . ' for user ' . $user->id);

        try {
            ActivityLog::create([
                'user_id'    => $user->id,
                'activity'   => 'Deposit',
                'details'    => "Deposited {$request->amount} {$currency}. Net added to wallet: {$netAmount} after fees.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'details' => $transaction->fresh()
        ]);
    }

    public function withdraw(Request $request)
    {
        $status = TransactionType::where('name', 'withdrawal')->first();
        if ($status && !$status->active) {
            return response()->json(['success' => false, 'message' => 'Withdrawals are temporarily disabled.'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:NGN,USD',
            'linked_account_id' => 'required|exists:linked_accounts,id'
        ]);

        $user = auth()->user();
        $kyc = $user->kyc;

        // 1. Basic KYC Guard
        if (!$kyc || $kyc->status !== 'verified' || $kyc->tier < 1) {
            return response()->json(['success' => false, 'message' => 'Verified KYC is required for withdrawals.'], 403);
        }

        // 2. Fetch global limit for this tier
        $tierSettings = \App\Models\KycSetting::where('tier', $kyc->tier)->first();
        $dailyLimit = $tierSettings ? $tierSettings->daily_limit : 0;

        // 3. Calculate today's usage (summing amounts)
        $todayWithdrawn = NewTransaction::where('user_id', $user->id)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['completed', 'pending'])
            ->whereDate('created_at', now())
            ->sum('amount');

        if (($todayWithdrawn + $request->amount) > $dailyLimit) {
            $remaining = max(0, $dailyLimit - $todayWithdrawn);
            return response()->json([
                'success' => false,
                'message' => "Daily limit exceeded for " . ($tierSettings->tier_name ?? "Tier $kyc->tier") . ". Remaining: " . number_format($remaining, 2)
            ], 403);
        }

        // 4. Ensure linked account belongs to user and currency rules
        $account = LinkedAccount::where('user_id', $user->id)->where('id', $request->linked_account_id)->firstOrFail();

        // Banks must match the withdrawal currency; crypto wallets are universal
        if ($account->type === 'bank' && ($account->currency ?? '') !== $request->currency) {
            return response()->json([
                'success' => false,
                'message' => 'Selected bank account currency does not match withdrawal currency.'
            ], 422);
        }
        $chargeAmount = TransactionCharge::calculate('withdrawal', $request->amount, null);
        $totalDeduction = $request->amount + $chargeAmount;

        try {
            return DB::transaction(function () use ($user, $request, $totalDeduction, $account, $chargeAmount) {
                $wallet = Wallet::where('user_id', $user->id)
                    ->where('currency', $request->currency)
                    ->lockForUpdate()
                    ->first();

                $clearedCol = $request->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
                $available = $wallet ? $wallet->{$clearedCol} : 0;

                if (!$wallet || $available < $totalDeduction) {
                    throw new \Exception("Insufficient cleared {$request->currency} balance");
                }

                $txn = NewTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'withdrawal',
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'charge' => $chargeAmount,
                    'net_amount' => $totalDeduction,
                    'status' => 'pending',
                    'meta' => [
                        'bank' => $account->provider,
                        'acc_no' => $account->account_number,
                        'acc_name' => $account->account_name
                    ]
                ]);

                $wallet->decrement($clearedCol, $totalDeduction);
                $wallet->decrement('balance', $totalDeduction);

                // Log the activity
                try {
                    ActivityLog::create([
                        'user_id'    => $user->id,
                        'activity'   => 'Withdrawal',
                        'details'    => "Withdrew {$request->amount} {$request->currency} to {$account->provider}. Total deduction: {$totalDeduction}.",
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                } catch (\Throwable $e) {
                }

                return response()->json(['success' => true, 'details' => $txn->fresh()]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
