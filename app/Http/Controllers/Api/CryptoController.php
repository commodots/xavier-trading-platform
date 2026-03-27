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

        try {
            $result = app(TatumService::class)->withdraw($toAddress, $amount, $privateKey);

            DB::transaction(function () use ($user, $amount) {
                NewTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'withdrawal',
                    'amount' => $amount,
                    'currency' => 'USDT',
                    'status' => 'completed',
                ]);

                Wallet::where('user_id', $user->id)
                    ->where('currency', 'USD')
                    ->update([
                        'usd_cleared' => DB::raw("usd_cleared - $amount"),
                        'balance' => DB::raw("balance - $amount"),
                    ]);
            });

            return response()->json(['success' => true, 'tx_hash' => $result['txId'] ?? null]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Withdrawal failed'], 500);
        }
    }
}
