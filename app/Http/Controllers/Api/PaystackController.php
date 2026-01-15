<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Wallet;
use App\Models\NewTransaction;
use App\Services\Payments\Paystack\PaystackService;
use Illuminate\Support\Facades\Auth;

class PaystackController extends Controller
{
    protected PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * ✅ Initiate a Paystack transaction
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $user = Auth::user();
        $reference = 'xavier_' . uniqid(); // 🔥 Always unique

        Log::info('💳 [Paystack:initiate] Request received', [
            'user' => $user->email ?? 'guest',
            'amount' => $request->amount,
            'reference' => $reference,
        ]);

        try {
            $result = $this->paystackService->initializePayment([
                'email' => $user->email,
                'amount' => $request->amount,
                'reference' => $reference,
                'callback_url' => config('services.paystack.callback_url', env('PAYSTACK_CALLBACK_URL')),
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'wallet_funding',
                ],
            ]);

            if ($result['status'] === 'failed') {
                Log::error('❌ Paystack:initiate failed', ['error' => $result]);
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Unable to initiate transaction.'
                ], 400);
            }

            Log::info('📡 [Paystack:initiate] Success', ['reference' => $result['reference']]);

            return response()->json([
                'success' => true,
                'data' => [
                    'reference' => $result['reference'],
                    'authorization_url' => $result['authorization_url'],
                    'access_code' => $result['access_code'],
                ]
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
            $result = $this->paystackService->verifyPayment($reference);

            Log::info('📡 [Paystack:verify] Response received', ['result' => $result]);

            if ($result['status'] === 'success') {
                $userId = $result['metadata']['user_id'] ?? null;

                if (!$userId) {
                    Log::error('❌ [Paystack:verify] No user_id in metadata', [
                        'reference' => $reference,
                        'metadata' => $result['metadata']
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid transaction metadata.'
                    ], 400);
                }

                $user = \App\Models\User::find($userId);

                if (!$user) {
                    Log::error('❌ [Paystack:verify] User not found', [
                        'reference' => $reference,
                        'user_id' => $userId
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found.'
                    ], 404);
                }

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $userId, 'currency' => 'NGN'],
                    ['balance' => 0, 'status' => 'active']
                );

                $wallet->increment('balance', $result['amount']);

                

                Log::info('✅ [Paystack:verify] Wallet credited', [
                    'user' => $user->email,
                    'amount' => $result['amount'],
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

    /**
     * Handle Paystack webhook callback and redirects
     */
    public function callback(Request $request)
    {
        // Handle GET requests (redirects after payment)
        if ($request->isMethod('get')) {
            return $this->handleRedirect($request);
        }

        // Handle POST requests (webhooks)
        return $this->handleWebhook($request);
    }

    /**
     * Handle user redirect after payment completion
     */
    private function handleRedirect(Request $request)
    {
        $reference = $request->query('reference');
        $trxref = $request->query('trxref');

        Log::info('🔄 [Paystack:redirect] User redirected after payment', [
            'reference' => $reference,
            'trxref' => $trxref
        ]);

        if (!$reference) {
            Log::warning('❌ [Paystack:redirect] No reference provided');
            return redirect('/wallet?payment_error=no_reference');
        }

        try {
            // Verify the payment status
            $result = $this->paystackService->verifyPayment($reference);

            if ($result['status'] === 'success') {
                // For local development, also update wallet immediately (normally done via webhook)
                $this->processSuccessfulPayment($result, $reference);

                // Redirect back to wallet with success info
                return redirect('/wallet?payment_success=' . $result['amount'] . '&reference=' . $reference);
            } else {
                // Payment failed or pending
                Log::warning('⚠️ [Paystack:redirect] Payment not successful', [
                    'reference' => $reference,
                    'status' => $result['status']
                ]);
                return redirect('/wallet?payment_error=payment_failed');
            }
        } catch (\Throwable $e) {
            Log::error('🔥 [Paystack:redirect] Exception during verification', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            return redirect('/wallet?payment_error=verification_error');
        }
    }

    /**
     * Handle Paystack webhook
     */
    private function handleWebhook(Request $request)
    {
        Log::info('🔗 [Paystack:webhook] Webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        try {
            // Verify webhook signature
            $signature = $request->header('x-paystack-signature');
            $secret = config('services.paystack.secret_key');

            if (!$this->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
                Log::warning('❌ [Paystack:webhook] Invalid signature', [
                    'signature' => $signature
                ]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $payload = $request->all();

            // Only process successful payments
            if (($payload['event'] ?? null) === 'charge.success') {
                $data = $payload['data'];
                $reference = $data['reference'];
                $userId = $data['metadata']['user_id'] ?? null;

                Log::info('🔍 [Paystack:webhook] Processing charge.success', [
                    'reference' => $reference,
                    'user_id' => $userId,
                    'amount' => $data['amount'] ?? 0,
                    'metadata' => $data['metadata'] ?? []
                ]);

                if (!$userId) {
                    Log::error('❌ [Paystack:webhook] No user_id in metadata', [
                        'reference' => $reference,
                        'metadata' => $data['metadata'] ?? []
                    ]);
                    return response()->json(['error' => 'Invalid metadata'], 400);
                }

                $user = \App\Models\User::find($userId);

                if (!$user) {
                    Log::error('❌ [Paystack:webhook] User not found', [
                        'reference' => $reference,
                        'user_id' => $userId
                    ]);
                    return response()->json(['error' => 'User not found'], 404);
                }

                // Check if transaction already processed (by webhook or redirect)
                $existingTransaction = \App\Models\WalletTransaction::where('reference', $reference)->first();
                if ($existingTransaction) {
                    Log::info('⚠️ [Paystack:webhook] Transaction already processed', [
                        'reference' => $reference,
                        'existing_transaction_id' => $existingTransaction->id
                    ]);
                    return response()->json(['message' => 'Already processed'], 200);
                }

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $userId, 'currency' => 'NGN'],
                    ['balance' => 0, 'status' => 'active']
                );

                $amount = $data['amount'] / 100; // Convert from kobo

                Log::info('💰 [Paystack:webhook] Updating wallet', [
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
                    'current_balance' => $wallet->balance,
                    'amount_to_add' => $amount
                ]);

                $wallet->increment('balance', $amount);

                Log::info('✅ [Paystack:webhook] Wallet balance updated', [
                    'new_balance' => $wallet->fresh()->balance
                ]);

                // Record the transaction
                $transaction = \App\Models\WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $userId,
                    'type' => 'credit',
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'reference' => $reference,
                    'description' => 'Wallet funding via Paystack',
                    'status' => 'completed',
                    'metadata' => $data,
                ]);

                Log::info('✅ [Paystack:webhook] Transaction created', [
                    'transaction_id' => $transaction->id,
                    'user' => $user->email,
                    'amount' => $amount,
                    'reference' => $reference,
                    'new_balance' => $wallet->fresh()->balance,
                ]);
            }

            return response()->json(['message' => 'Webhook processed'], 200);

        } catch (\Throwable $e) {
            Log::error('🔥 [Paystack:webhook] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Server error'], 500);
        }
    }

    /**
     * Process successful payment (used for local development when webhooks aren't available)
     */
    private function processSuccessfulPayment(array $result, string $reference)
    {
        try {
            $userId = $result['metadata']['user_id'] ?? null;

            if (!$userId) {
                Log::error('❌ [Paystack:redirect] No user_id in metadata for immediate processing', [
                    'reference' => $reference,
                    'metadata' => $result['metadata'] ?? []
                ]);
                return;
            }

            $user = \App\Models\User::find($userId);

            if (!$user) {
                Log::error('❌ [Paystack:redirect] User not found for immediate processing', [
                    'reference' => $reference,
                    'user_id' => $userId
                ]);
                return;
            }

            // Check if transaction already processed
            $existingTransaction = \App\Models\WalletTransaction::where('reference', $reference)->first();
            if ($existingTransaction) {
                Log::info('⚠️ [Paystack:redirect] Transaction already processed', [
                    'reference' => $reference
                ]);
                return;
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'currency' => 'NGN'],
                ['balance' => 0, 'status' => 'active']
            );

            $amount = $result['amount'];

            Log::info('💰 [Paystack:redirect] Updating wallet immediately', [
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'current_balance' => $wallet->balance,
                'amount_to_add' => $amount
            ]);

            $wallet->increment('balance', $amount);

            // Record the transaction
            $transaction = \App\Models\WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'description' => 'Wallet funding via Paystack',
                'status' => 'completed',
                'metadata' => $result,
            ]);

            Log::info('✅ [Paystack:redirect] Wallet credited immediately', [
                'transaction_id' => $transaction->id,
                'user' => $user->email,
                'amount' => $amount,
                'reference' => $reference,
                'new_balance' => $wallet->fresh()->balance,
            ]);
        } catch (\Throwable $e) {
            Log::error('🔥 [Paystack:redirect] Exception during immediate processing', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verify Paystack webhook signature
     */
    private function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $computedSignature = hash_hmac('sha512', $payload, $secret);
        return hash_equals($computedSignature, $signature);
    }
}
