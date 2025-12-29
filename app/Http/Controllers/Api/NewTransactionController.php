<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Models\TransactionCharge;
use App\Models\TransactionType;

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

        $request->validate(['amount' => 'required|numeric|min:1']);
        $user = auth()->user();
        $currency = $request->currency ?? 'NGN';

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

        // 3. IMPORTANT: Update the Wallet balance, not just the user balance
        $wallet = Wallet::where('user_id', $user->id)->where('currency', $currency)->first();
        if ($wallet) {
            $wallet->increment('balance', $netAmount);
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
            return response()->json([
                'success' => false,
                'message' => 'Withdrawals are temporarily disabled.'
            ], 403);
        }

        $request->validate(['amount' => 'required|numeric|min:1']);
        $user = auth()->user();
        $currency = $request->currency ?? 'NGN';


        $chargeAmount = TransactionCharge::calculate('withdrawal', $request->amount, null);
        $totalDeduction = $request->amount + $chargeAmount;

        $wallet = Wallet::where('user_id', $user->id)->where('currency', $currency)->first();

        if (!$wallet || $wallet->balance < $totalDeduction) {
            return response()->json(['message' => 'Insufficient wallet balance'], 400);
        }
        $txn = NewTransaction::create([
            'user_id'    => $user->id,
            'type'       => 'withdrawal',
            'amount'     => $request->amount,
            'currency'   => $currency,
            'net_amount' => $totalDeduction,
            'status'     => 'completed',
        ]);


        $wallet->decrement('balance', $totalDeduction);

        TransactionCharge::calculate('withdrawal', $request->amount, $txn);

        return response()->json([
            'success' => true,
            'details' => $txn->fresh()
        ]);
    }
}
