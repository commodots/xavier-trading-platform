<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TransactionCharge;

class NewTransactionController extends Controller
{

    public function index()
    {

        return response()->json(auth()->user()->transactions()->latest()->get());
    }

    public function deposit(Request $request)
    {
        $charge = TransactionCharge::calculate('deposit', $request->amount);

        $transaction = NewTransaction::create([
            'user_id' => auth()->id(),
            'type' => 'deposit',
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'NGN',
            'charge' => $charge,
            'net_amount' => $request->amount - $charge,
            'status' => 'completed'
        ]);
        return response()->json($transaction);
    }


    public function withdraw(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        $user = auth()->user();


        $chargeAmount = TransactionCharge::calculate('withdrawal', $request->amount);
        $totalDeduction = $request->amount + $chargeAmount;


        if ($user->balance < $totalDeduction) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        $user->decrement('balance', $totalDeduction);

        $txn = NewTransaction::create([
            'user_id'    => $user->id,
            'type'       => 'withdrawal',
            'amount'     => $request->amount,
            'charge'     => $chargeAmount,
            'net_amount' => $totalDeduction,
            'status'     => 'pending',
        ]);

        return response()->json($txn);
    }
}
