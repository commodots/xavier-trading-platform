<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use App\Models\Wallet;
use App\Models\LinkedAccount;
use Illuminate\Http\Request;
use App\Models\TransactionCharge;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

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

        // 1. Create transaction record
        $transaction = NewTransaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $request->amount,
            'currency' => $currency,
            'status' => 'completed',
            'net_amount' => $request->amount
        ]);

        // 2. Calculate fee and update the record
        $charge = TransactionCharge::calculate('deposit', $request->amount, $transaction);
        $netAmount = $request->amount - $charge;

        $transaction->update(['net_amount' => $netAmount]);

        // 3. Update the Wallet balance
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            ['balance' => 0]
        );
        $wallet->increment('balance', $netAmount);

        return response()->json([
            'success' => true,
            'details' => $transaction->fresh()
        ]);
    }

    public function withdraw(Request $request)
    {
        $status = TransactionType::where('name', 'withdrawal')->first();
        if ($status && !$status->active) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawals are temporarily disabled.'
            ], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:NGN,USD',
            'linked_account_id' => 'required|exists:linked_accounts,id'
        ]);

        $user = auth()->user();
        $currency = $request->currency;

        $account = LinkedAccount::where('user_id', $user->id)
            ->where('id', $request->linked_account_id)
            ->firstOrFail();

        $chargeAmount = TransactionCharge::calculate('withdrawal', $request->amount, null);
        $totalDeduction = $request->amount + $chargeAmount;

        try {
            return DB::transaction(function () use ($user, $currency, $request, $totalDeduction, $account) {
                $wallet = Wallet::where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->lockForUpdate() 
                    ->first();

                if (!$wallet || $wallet->balance < $totalDeduction) {
                    throw new \Exception("Insufficient $currency wallet balance");
                }

                $txn = NewTransaction::create([
                    'user_id'    => $user->id,
                    'type'       => 'withdrawal',
                    'amount'     => $request->amount,
                    'currency'   => $currency,
                    'net_amount' => $totalDeduction,
                    'status'     => 'completed',
                    'meta'       => [
                        'bank_name'      => $account->provider,
                        'account_number' => $account->account_number,
                        'account_name'   => $account->account_name
                    ]
                ]);

                $wallet->decrement('balance', $totalDeduction);
                TransactionCharge::calculate('withdrawal', $request->amount, $txn);

                return response()->json([
                    'success' => true,
                    'details' => $txn->fresh()
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    } 
}