<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LinkedAccount;
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
            'provider' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
        ]);

        $account = LinkedAccount::create([
            'user_id' => Auth::id(),
            'type' => $request->type,
            'provider' => $request->provider,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'is_verified' => false, // Default for new accounts
        ]);

        return response()->json(['success' => true, 'data' => $account]);
    }
}