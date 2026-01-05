<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LinkedAccount;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LinkedAccountController extends Controller
{
    // Fetch all accounts for the user
    public function index()
    {
        $accounts = LinkedAccount::where('user_id', Auth::id())->get();
        return response()->json(['success' => true, 'data' => $accounts]);
    }

    // Add a new account
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:bank,crypto_wallet',
            'currency' => 'required|string|in:NGN,USD',
            'provider' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
        ]);

        $account = LinkedAccount::create([
            'user_id' => Auth::id(),
            'type' => $request->type,
            'currency' => $request->currency,
            'provider' => $request->provider,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'is_verified' => false, // Default for new accounts
        ]);

        
        try {
            $accountType = $request->type === 'bank' ? 'Bank Account' : 'Crypto Wallet';
            
            ActivityLog::create([
                'user_id'    => Auth::id(),
                'activity'   => 'Linked Account Added',
                'details'    => "Added a new {$accountType} ({$request->provider}) ending in ..." . substr($request->account_number, -4),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            
        }
        return response()->json(['success' => true, 'data' => $account]);
    }
}