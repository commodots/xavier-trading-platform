<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoWallet;
use App\Models\LinkedAccount;
use App\Models\NewTransaction;
use App\Models\TransactionCharge;
use App\Models\TransactionType;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Notifications\WithdrawalOtpNotification;
class NewTransactionController extends Controller
{
    private function resolveModels($user, ?Request $request = null)
    {
        // Prioritize the query parameter, fallback to the user's saved mode
        $mode = $request ? $request->query('mode', $user->trading_mode) : $user->trading_mode;
        $isDemo = $mode === 'demo';

        return (object) [
            'isDemo' => $isDemo,
            'wallet' => $isDemo ? new DemoWallet : new Wallet,
            'transaction' => $isDemo ? new DemoTransaction : new NewTransaction,
        ];
    }

    public function index(Request $request)
    {
        $models = $this->resolveModels(auth()->user(), $request);

        return response()->json(
            $models->transaction->where('user_id', auth()->id())->latest()->limit(10)->get()
        );
    }

    public function deposit(Request $request)
    {
        // Check if the deposit service is active
        $depositService = TransactionType::where('name', 'deposit')->first();
        if ($depositService && ! $depositService->active) {
            return response()->json(['success' => false, 'message' => 'Deposits are temporarily disabled.'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:NGN,USD',
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
                'net_amount' => $netAmount,
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
                    'user_id' => $user->id,
                    'activity' => 'Deposit',
                    'details' => "Deposited {$request->amount} {$request->currency}. Net added to wallet: {$netAmount} after fees.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
            }

            return response()->json([
                'success' => true,
                'details' => $transaction->fresh(),
            ]);
        });
    }

    public function sendOtp(Request $request)
    {
        $user = auth()->user();

        // Generate a random 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store in cache for 5 minutes (300 seconds)
        $cacheKey = 'withdrawal_otp_' . $user->id;
        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        $user->notify(new WithdrawalOtpNotification($otp));

        Log::info("Withdrawal OTP generated for user {$user->id}", [
            'ip' => $request->ip(),
            'otp_preview' => substr($otp, 0, 2) . '****' // Log only part for security auditing
        ]);

        // For development/demo purposes, we'll return success. 
        // In production, ensure the actual delivery service (Mail/SMS) succeeded.
        return response()->json([
            'success' => true,
            'message' => 'A verification code has been sent to your registered email/phone.',
            // Remove 'debug_otp' in production!
            'debug_otp' => app()->environment('local') ? $otp : null
        ]);
    }

    public function withdraw(Request $request)
    {
        $user = auth()->user();
        $models = $this->resolveModels($user);

        // Check if the withdrawal service is active
        $withdrawalService = TransactionType::where('name', 'withdrawal')->first();
        if ($withdrawalService && ! $withdrawalService->active) {
            return response()->json(['success' => false, 'message' => 'Withdrawals are temporarily disabled.'], 403);
        }

        $rules = [
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:NGN,USD',
            'linked_account_id' => 'required|exists:linked_accounts,id',
        ];

        if (!$models->isDemo) {
            $rules['withdrawal_otp'] = 'required|string';
        }

        $request->validate($rules);


        if (!$models->isDemo) {
            $cacheKey = 'withdrawal_otp_' . $user->id;

            $cachedOtp = Cache::get($cacheKey);

            if (!$cachedOtp || $request->input('withdrawal_otp') !== $cachedOtp) {
                return response()->json(['success' => false, 'message' => 'Invalid withdrawal OTP.'], 403);
            }

            // Clear the OTP immediately after successful verification to prevent reuse
            Cache::forget($cacheKey);
        }
        $kyc = $user->kyc;

        // KYC Guard (Only enforced for LIVE mode!)
        if (! $models->isDemo) {
            if (! $kyc || $kyc->status !== 'verified' || $kyc->tier < 1) {
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

        if (! $models->isDemo && ($todayWithdrawn + $request->amount) > $dailyLimit) {
            $remaining = max(0, $dailyLimit - $todayWithdrawn);

            return response()->json(['success' => false, 'message' => 'Daily limit exceeded. Remaining: ' . number_format($remaining, 2)], 403);
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

                if (! $wallet || $available < $totalDeduction) {
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
                        'mode' => $user->trading_mode,
                    ],
                ]);

                $wallet->decrement($clearedCol, $totalDeduction);
                $wallet->decrement('balance', $totalDeduction);

                try {
                    ActivityLog::create([
                        'user_id' => $user->id,
                        'activity' => 'Withdrawal',
                        'details' => "Withdrew {$request->amount} {$request->currency}. Total deduction: {$totalDeduction}.",
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                } catch (\Throwable $e) {
                }

                return response()->json(['success' => true, 'details' => $txn->fresh()]);
            });
        } catch (\Exception $e) {
            Log::error('Withdrawal transaction failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Unable to complete withdrawal request at this time.'], 500);
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

        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $transaction]);
    }
}
