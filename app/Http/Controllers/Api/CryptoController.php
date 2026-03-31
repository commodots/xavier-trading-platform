<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CryptoAddress;
use App\Models\NewTransaction;
use App\Models\Wallet;
use App\Services\TatumService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CryptoController extends Controller
{
    public function getAddress(Request $request)
    {
        $user = $request->user();

        $address = CryptoAddress::where('user_id', $user->id)->first();

        if (! $address) {
            app(TatumService::class)->generateTronAddress($user->id);
            $address = CryptoAddress::where('user_id', $user->id)->first();
        }

        return response()->json([
            'success' => true,
            'address' => $address->address,
            'qr_code_url' => $address->qr_code_url,
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'address' => 'required|string',
        ]);

        $user = $request->user();
        $amount = (float) $request->amount;
        $toAddress = $request->address;

        $wallet = Wallet::where('user_id', $user->id)->where('currency', 'USD')->first();

        if (! $wallet || $wallet->usd_cleared < $amount) {
            return response()->json(['success' => false, 'message' => 'Insufficient balance'], 400);
        }

        $crypto = CryptoAddress::where('user_id', $user->id)->first();

        if (! $crypto) {
            return response()->json(['success' => false, 'message' => 'No crypto address found'], 400);
        }

        $privateKey = decrypt($crypto->private_key);

        // Create a pending transaction and deduct balance first (Safety)
        $transaction = DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)
                ->where('currency', 'USD')
                ->lockForUpdate()
                ->first();

            if (!$wallet || $wallet->usd_cleared < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $wallet->decrement('usd_cleared', $amount);
            $wallet->decrement('balance', $amount);

            return NewTransaction::create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'amount' => $amount,
                'currency' => 'USDT',
                'status' => 'pending',
            ]);
        });

        try {
            $result = app(TatumService::class)->withdraw($toAddress, $amount, $privateKey);
            $transaction->update(['status' => 'completed', 'tx_hash' => $result['txId'] ?? null]);
            
            return response()->json(['success' => true, 'tx_hash' => $result['txId'] ?? null]);
        } catch (\Exception $e) {
            // Refund if external call fails
            DB::transaction(function () use ($user, $amount, $transaction) {
                Wallet::where('user_id', $user->id)->where('currency', 'USD')->increment('usd_cleared', $amount);
                $transaction->update(['status' => 'failed']);
            });
            return response()->json(['success' => false, 'message' => 'Withdrawal failed'], 500);
        }
    }
}
