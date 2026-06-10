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
use App\Notifications\WithdrawalOtpNotification;
use App\Services\WithdrawalProtectionService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        Log::info('Deposit initiated for user '.$user->id.' with amount '.$request->amount);

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

            $wallet = $models->wallet->firstOrCreate(
                ['user_id' => $user->id, 'currency' => $request->currency]
            );

            $clearedCol = $request->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $oldBalance = $wallet->{$clearedCol};

            $transaction = $models->transaction->create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => 'completed',
                'net_amount' => $netAmount,
                'charge' => $charge,
                'meta' => [
                    'old_balance' => $oldBalance,
                    'new_balance' => $oldBalance + $netAmount,
                ],
            ]);

            Log::info('Transaction created with ID '.$transaction->id);

            $wallet->increment($clearedCol, $netAmount);
            $wallet->increment('balance', $netAmount);

            Log::info('Wallet balance incremented by '.$netAmount.' for user '.$user->id);

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

        // Rate limiting on OTP requests
        $rateLimitKey = 'otp_request_'.$user->id;
        $requestCount = Cache::get($rateLimitKey, 0);
        if ($requestCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please try again in 15 minutes.',
            ], 429);
        }

        // Generate a random 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store in cache for 5 minutes (300 seconds)
        $cacheKey = 'withdrawal_otp_'.$user->id;
        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        // Increment request counter with 15-minute window
        Cache::put($rateLimitKey, $requestCount + 1, now()->addMinutes(15));

        $user->notify(new WithdrawalOtpNotification($otp));

        Log::info("Withdrawal OTP generated for user {$user->id}", [
            'ip' => $request->ip(),
            'otp_preview' => substr($otp, 0, 2).'****', // Log only part for security auditing
        ]);

        // For development/demo purposes, we'll return success.
        // In production, ensure the actual delivery service (Mail/SMS) succeeded.
        return response()->json([
            'success' => true,
            'message' => 'A verification code has been sent to your registered email/phone.',
        ]);
    }

    public function withdraw(Request $request)
    {
        // Use the centralized WithdrawalController via the /security/withdrawals route.
        // This endpoint is deprecated. Use POST /security/withdrawals instead.
        return response()->json([
            'message' => 'Use POST /security/withdrawals for withdrawal requests.',
        ], 301);
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
