<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;

class PaystackController extends Controller
{
    /**
     * ✅ Initiate a Paystack transaction
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $user = Auth::user();
        $amount = (int) $request->amount * 100; // Convert to kobo
        $reference = 'xavier_' . uniqid(); // 🔥 Always unique

        Log::info('💳 [Paystack:initiate] Request received', [
            'user' => $user->email ?? 'guest',
            'amount' => $request->amount,
            'reference' => $reference,
        ]);

        try {
            $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => $amount,
                    'reference' => $reference,
                    'callback_url' => env('PAYSTACK_CALLBACK_URL', 'http://127.0.0.1:8000/api/paystack/callback'),
                ]);

            Log::info('PAYSTACK RAW RESPONSE', ['response' => $response->body()]);

            if ($response->failed()) {
                Log::error('❌ Paystack:initiate failed', ['error' => $response->json()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to initiate transaction.'
                ], 400);
            }

            $data = $response->json();

            Log::info('📡 [Paystack:initiate] API response', ['body' => $data]);

            return response()->json([
                'success' => true,
                'data' => $data['data']
            ]);
        } catch (\Throwable $e) {
            Log::error('🔥 Paystack:initiate exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error while initiating payment.'
            ], 500);
        }
    }

    /**
     * ✅ Verify Paystack transaction and credit wallet
     */
    public function verify($reference)
    {
        Log::info('🔍 [Paystack:verify] Checking transaction', ['reference' => $reference]);

        try {
            $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                ->get("https://api.paystack.co/transaction/verify/{$reference}");

            $data = $response->json();

            Log::info('📡 [Paystack:verify] Response received', ['body' => $data]);

            if (($data['data']['status'] ?? null) === 'success') {
                $user = Auth::user();
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id, 'currency' => 'NGN'],
                    ['balance' => 0, 'status' => 'active']
                );

                $wallet->increment('balance', $data['data']['amount'] / 100);

                Log::info('✅ [Paystack:verify] Wallet credited', [
                    'user' => $user->email,
                    'amount' => $data['data']['amount'] / 100,
                    'new_balance' => $wallet->balance,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Wallet funded successfully!',
                    'balance' => $wallet->balance
                ]);
            }

            Log::warning('⚠️ [Paystack:verify] Transaction verification failed', [
                'reference' => $reference,
                'status' => $data['data']['status'] ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction verification failed.'
            ], 400);
        } catch (\Throwable $e) {
            Log::error('🔥 [Paystack:verify] Exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error while verifying transaction.'
            ], 500);
        }
    }
}
