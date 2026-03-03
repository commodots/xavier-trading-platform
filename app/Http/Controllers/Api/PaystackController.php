<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Wallet;
use App\Models\NewTransaction;
use App\Services\Payments\Paystack\PaystackService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoWallet;

class PaystackController extends Controller
{
    protected PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    private function resolveModels($user)
    {
        $isDemo = $user->trading_mode === 'demo';
        return (object) [
            'isDemo'      => $isDemo,
            'wallet'      => $isDemo ? new DemoWallet() : new Wallet(),
            'transaction' => $isDemo ? new DemoTransaction() : new NewTransaction(),
        ];
    }

    public function initiate(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:100']);

        $user = Auth::user();
        $reference = 'xavier_' . uniqid();
        $models = $this->resolveModels($user);

        // If in Demo mode, bypass Paystack and fund the demo wallet instantly
        if ($models->isDemo) {
            return DB::transaction(function () use ($user, $request, $reference, $models) {
                $wallet = $models->wallet->firstOrCreate(
                    ['user_id' => $user->id, 'currency' => 'NGN'],
                    ['balance' => 0, 'status' => 'active', 'ngn_cleared' => 0]
                );

                $wallet->increment('balance', $request->amount);
                $wallet->increment('ngn_cleared', $request->amount);

                $models->transaction->create([
                    'user_id' => $user->id,
                    'amount' => $request->amount,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'charge' => 0,
                    'net_amount' => $request->amount,
                    'currency' => 'NGN',
                    'meta' => ['reference' => $reference, 'gateway' => 'demo_instant', 'mode' => 'demo']
                ]);

                return response()->json([
                    'success' => true,
                    'is_demo' => true,
                    'message' => 'Demo account instantly funded!',
                    'data' => ['reference' => $reference, 'authorization_url' => null]
                ]);
            });
        }

        try {
            $result = $this->paystackService->initializePayment([
                'email' => $user->email,
                'amount' => $request->amount,
                'reference' => $reference,
                'callback_url' => config('services.paystack.callback_url'),
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'wallet_funding',
                ],
            ]);

            return response()->json(['success' => true, 'is_demo' => false, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('Paystack:initiate exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }

    public function verify($reference)
    {
        // Live verification logic
        // Check if transaction already processed locally to avoid lag and double crediting
        $existing = NewTransaction::where('meta->reference', $reference)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Already processed',
                'balance' => Wallet::where('user_id', $existing->user_id)->where('currency', 'NGN')->value('balance')
            ]);
        }

        try {
            $result = $this->paystackService->verifyPayment($reference);
            if ($result['status'] === 'success') {
                return $this->processSuccessfulPayment($result, $reference);
            }
            return response()->json(['success' => false, 'message' => 'Verification failed.'], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
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

        Log::info('[Paystack:redirect] User redirected after payment', [
            'reference' => $reference,
            'trxref' => $trxref
        ]);

        if (!$reference) {
            Log::warning('[Paystack:redirect] No reference provided');
            return redirect('/wallet?payment_error=no_reference');
        }

        try {
            // Verify the payment status
            $result = $this->paystackService->verifyPayment($reference);

            if ($result['status'] === 'success') {
                // For local development, also update wallet immediately (normally done via webhook)
                $this->processSuccessfulPayment($result, $reference);

                // Redirect back to wallet with success info
                return redirect('/wallet?payment_success=' . ($result['amount'] / 100) . '&reference=' . $reference);
            } else {
                // Payment failed or pending
                Log::warning('[Paystack:redirect] Payment not successful', [
                    'reference' => $reference,
                    'status' => $result['status']
                ]);
                return redirect('/wallet?payment_error=payment_failed');
            }
        } catch (\Throwable $e) {
            Log::error('[Paystack:redirect] Exception during verification', [
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
        Log::info('[Paystack:webhook] Webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        try {
            // Verify webhook signature
            $signature = $request->header('x-paystack-signature');
            $secret = config('services.paystack.secret_key');

            if (!$this->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
                Log::warning('[Paystack:webhook] Invalid signature', [
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

                $amount = $data['amount'] / 100; // Convert from kobo
                $charge = ($data['fees'] ?? 0) / 100; // Get actual Paystack charge
                $netAmount = $amount - $charge;

                Log::info('[Paystack:webhook] Processing charge.success', [
                    'reference' => $reference,
                    'user_id' => $userId,
                    'raw_amount' => $data['amount'] ?? 0,
                    'amount' => $amount,
                    'charge' => $charge,
                    'net_amount' => $netAmount,
                    'metadata' => $data['metadata'] ?? []
                ]);

                if (!$userId) {
                    Log::error('[Paystack:webhook] No user_id in metadata', [
                        'reference' => $reference,
                        'metadata' => $data['metadata'] ?? []
                    ]);
                    return response()->json(['error' => 'Invalid metadata'], 400);
                }

                $user = \App\Models\User::find($userId);

                if (!$user) {
                    Log::error('[Paystack:webhook] User not found', [
                        'reference' => $reference,
                        'user_id' => $userId
                    ]);
                    return response()->json(['error' => 'User not found'], 404);
                }

                // Check if transaction already processed (by webhook or redirect)
                $existingTransaction = NewTransaction::where('meta->reference', $reference)->first();
                if ($existingTransaction) {
                    Log::info('[Paystack:webhook] Transaction already processed', [
                        'reference' => $reference,
                        'existing_transaction_id' => $existingTransaction->id
                    ]);
                    return response()->json(['message' => 'Already processed'], 200);
                }

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $userId, 'currency' => 'NGN'],
                    ['balance' => 0, 'status' => 'active']
                );

                Log::info('[Paystack:webhook] Updating wallet', [
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
                    'current_balance' => $wallet->balance,
                    'amount_to_add' => $netAmount,
                    'charge' => $charge,
                    'net_amount' => $netAmount
                ]);

                $wallet->increment('balance', $netAmount);

                Log::info('[Paystack:webhook] Wallet balance updated', [
                    'new_balance' => $wallet->fresh()->balance
                ]);

                // Record the transaction
                $transaction =  NewTransaction::create([
                    'user_id' => $userId,
                    'amount' => $amount,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'charge' => $charge,
                    'net_amount' => $netAmount,
                    'currency' => 'NGN',
                    'meta' => [
                        'reference' => $reference,
                        'gateway' => 'paystack'
                    ]
                ]);


                Log::info('[Paystack:webhook] Transaction created', [
                    'transaction_id' => $transaction->id,
                    'user' => $user->email,
                    'amount' => $amount,
                    'reference' => $reference,
                    'new_balance' => $wallet->fresh()->balance,
                ]);
            }

            return response()->json(['message' => 'Webhook processed'], 200);
        } catch (\Throwable $e) {
            Log::error('[Paystack:webhook] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Server error'], 500);
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
    
    /**
     * Process successful payment (used for local development when webhooks aren't available)
     */
    private function processSuccessfulPayment(array $result, string $reference)
    {
        $userId = $result['metadata']['user_id'] ?? null;
        if (!$userId) return response()->json(['success' => false], 400);

        return DB::transaction(function () use ($result, $reference, $userId) {
            $existing = NewTransaction::where('meta->reference', $reference)->first();
            if ($existing) return response()->json(['success' => true]);

            $amount = $result['amount']/100;
            $charge = ($result['fees'] ?? 0) / 100;
            $netAmount = $amount - $charge;

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'currency' => 'NGN'],
                    ['balance' => 0, 'status' => 'active', 'ngn_cleared' => 0, 'ngn_uncleared' => 0, 'locked' => 0]
            );

            $wallet->increment('balance', $netAmount);
            $wallet->increment('ngn_cleared', $netAmount);

            NewTransaction::create([
                'user_id' => $userId,
                'amount' => $amount,
                'type' => 'deposit',
                'status' => 'completed',
                'charge' => $charge,
                'net_amount' => $netAmount,
                'currency' => 'NGN',
                'meta' => ['reference' => $reference, 'gateway' => 'paystack']
            ]);

            return response()->json(['success' => true, 'balance' => $wallet->balance]);
        });
    }
}
