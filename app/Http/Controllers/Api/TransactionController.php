<?php

// app/Http/Controllers/Api/TransactionController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{ 
  
    public function index()
    {
      $user = Auth::user();

      return response()->json(auth()->user()->transactions()->latest()->get());
    }

    public function deposit(Request $request)
    {
        
        $transaction = Transaction::create([
            'user_id' => auth()->id(),
            'type' => 'deposit',
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'NGN',
            'status' => 'completed'
        ]);
        return response()->json($transaction);
    }

   
    public function withdraw(Request $request)
    {
        $transaction = Transaction::create([
            'user_id' => auth()->id(),
            'type' => 'withdrawal',
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'NGN',
            'status' => 'pending'
        ]);
        return response()->json($transaction);
    }
}