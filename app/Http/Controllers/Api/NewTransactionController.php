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
        // Strictly use the user's trading mode to prevent environment spoofing via query params
        $isDemo = $user->trading_mode === 'demo';

        return (object) [
            'isDemo' => $isDemo,
            'wallet' => $isDemo ? \App\Models\Demo\DemoWallet::class : Wallet::class,
            'transaction' => $isDemo ? \App\Models\Demo\DemoTransaction::class : NewTransaction::class,
        ];
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $models = $this->resolveModels($user, $request);

        return response()->json(
            $models->transaction::where('user_id', $user->id)->latest()->limit(10)->get()
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

            $wallet = $models->wallet::firstOrCreate(
                ['user_id' => $user->id, 'currency' => $request->currency]
            );

            $clearedCol = $request->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $unclearedCol = $request->currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';
            $oldBalance = $wallet->{$clearedCol};

            $transaction = $models->transaction::create([
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
            
            // Do not manually calculate balance in controllers
            $wallet->refreshBalance();

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
        // Deprecated: OTP generation is now handled centrally by WithdrawalService via WithdrawalController
        return response()->json([
            'message' => 'This endpoint is deprecated. Withdrawal OTPs are now sent automatically via POST /security/withdrawals.',
        ], 410);
    }

    public function withdraw(Request $request)
    {
        return response()->json([
            'message' => 'Withdrawals have moved to the Unified Security Flow. Please use POST /security/withdrawals.',
            'target_url' => url('/api/security/withdrawals')
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
