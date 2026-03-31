<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CryptoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $incomingSecret = $request->header('x-api-key');
        if ($incomingSecret !== config('services.crypto.api_key')) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $address = $request->input('address');
        $amount = (float) $request->input('amount');
        $txHash = $request->input('txId'); // Tatum sends txId in the payload

        $cryptoAddress = \App\Models\CryptoAddress::where('address', $address)->first();

        if (! $cryptoAddress || $amount <= 0 || ! $txHash) {
            return response()->json(['status' => 'ignored']);
        }

        return DB::transaction(function () use ($cryptoAddress, $amount, $txHash) {
            // Idempotency check: Ensure this transaction hasn't been processed
            $exists = NewTransaction::where('tx_hash', $txHash)->exists();
            if ($exists) {
                return response()->json(['status' => 'already_processed']);
            }

            NewTransaction::create([
                'user_id' => $cryptoAddress->user_id,
                'type' => 'deposit',
                'amount' => $amount,
                'currency' => 'USDT',
                'status' => 'completed',
                'tx_hash' => $txHash,
            ]);

            Wallet::where('user_id', $cryptoAddress->user_id)
                ->where('currency', 'USD')
                ->update([
                    'usd_cleared' => DB::raw("usd_cleared + $amount"),
                    'balance' => DB::raw("balance + $amount"),
                ]);

            return response()->json(['status' => 'ok']);
        });
    }
}
