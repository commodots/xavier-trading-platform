<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoWallet;
use App\Models\FxRate;
use App\Models\NewTransaction;
use App\Models\Wallet;
use App\Services\Payments\Paystack\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'isDemo' => $isDemo,
            'wallet' => $isDemo ? new DemoWallet : new Wallet,
            'transaction' => $isDemo ? new DemoTransaction : new NewTransaction,
        ];
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'currency' => 'nullable|in:NGN,USD',
        ]);

        $user = Auth::user();
        $reference = 'xavier_'.uniqid();
        $models = $this->resolveModels($user);
        $targetCurrency = $request->input('currency', 'NGN');

        $paystackAmount = $request->amount * 100; // Paystack expects amount in kobo/subunits
        $metadata = [
            'user_id' => $user->id,
            'type' => 'wallet_funding',
            'target_currency' => $targetCurrency,
        ];

        if ($targetCurrency === 'USD') {
            $fxRate = FxRate::where('from_currency', 'NGN')
                ->where('to_currency', 'USD')
                ->first();
            if (! $fxRate) {
                return response()->json(['success' => false, 'message' => 'FX rate not available for USD conversion.', 'data' => null], 400);
            }
            $paystackAmount = round($request->amount * $fxRate->effective_rate, 2);
            $metadata['usd_amount'] = $request->amount;
            $metadata['fx_rate'] = $fxRate->effective_rate;
            $metadata['ngn_amount'] = $paystackAmount / 100;
        }

        // If in Demo mode, bypass Paystack and fund the demo wallet instantly
        if ($models->isDemo) {
            return DB::transaction(function () use ($user, $request, $reference, $models, $targetCurrency) {
                $initData = [
                    'balance' => 0, 
                    'status' => 'active', 
                    'ngn_cleared' => 0, 
                    'usd_cleared' => 0
                ];

                $wallet = $models->wallet->firstOrCreate(
                    ['user_id' => $user->id, 'currency' => $targetCurrency],
                    $initData
                );

                $wallet->increment('balance', $request->amount);
                if ($targetCurrency === 'NGN') {
                    $wallet->increment('ngn_cleared', $request->amount);
                } elseif ($targetCurrency === 'USD') {
                    $wallet->increment('usd_cleared', $request->amount);
                }

                $models->transaction->create([
                    'user_id' => $user->id,
                    'amount' => $request->amount,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'charge' => 0,
                    'net_amount' => $request->amount,
                    'currency' => $targetCurrency,
                    'meta' => ['reference' => $reference, 'gateway' => 'demo_instant', 'mode' => 'demo'],
                ]);

                return response()->json([
                    'success' => true,
                    'is_demo' => true,
                    'message' => "Successfully deposited " . ($targetCurrency === 'USD' ? '$' : '₦') . number_format($request->amount, 2) . " into your " . strtoupper($targetCurrency) . " wallet.",
                    'data' => ['reference' => $reference, 'authorization_url' => null],
                ]);
            });
        }

        try {
            $result = $this->paystackService->initializePayment([
                'email' => $user->email,
                'amount' => $paystackAmount,
                'currency' => $targetCurrency,
                'reference' => $reference,
                'callback_url' => route('paystack.callback'),
                'metadata' => $metadata,
            ]);

            if ($result['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'is_demo' => false,
                    'data' => $result,
                    'fx_details' => ($targetCurrency === 'USD') ? [
                        'rate' => $fxRate->effective_rate,
                        'ngn_total' => $paystackAmount / 100
                    ] : null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'is_demo' => false,
                    'message' => $result['message'] ?? 'Payment initialization failed.',
                    'data' => $result,
                ], 400);
            }
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
                'balance' => Wallet::where('user_id', $existing->user_id)->where('currency', $existing->currency)->value('balance'),
            ]);
        }

        try {
            $result = $this->paystackService->verifyPayment($reference);
            if ($result['status'] === 'success') {
                return $this->processSuccessfulPayment($result, $reference);
            }

                return response()->json(['success' => false, 'message' => 'Verification failed.', 'data' => null], 400);
        } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Server error.', 'data' => null], 500);
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
            'trxref' => $trxref,
        ]);

        if (! $reference) {
            Log::warning('[Paystack:redirect] No reference provided');

            return redirect('/wallet?payment_error=no_reference');
        }

        try {
            // Verify the payment status
            $result = $this->paystackService->verifyPayment($reference);

            if ($result['status'] === 'success') {
                // For local development, also update wallet immediately (normally done via webhook)
                $this->processSuccessfulPayment($result, $reference);

                // Calculate credited amount for success message
                $targetCurrency = $result['metadata']['target_currency'] ?? 'NGN';
                $amount = $result['amount'];
                $creditedAmount = $amount;
                $appliedRate = $result['metadata']['fx_rate'] ?? null;

                if ($targetCurrency === 'USD') {
                    $usdAmount = $result['metadata']['usd_amount'] ?? null;
                    if ($usdAmount) {
                        $creditedAmount = $usdAmount;
                    } else {
                        // Fallback to conversion
                        $fxRate = FxRate::where('from_currency', 'NGN')
                            ->where('to_currency', 'USD')
                            ->first();
                        if ($fxRate) {
                            $creditedAmount = round($amount / $fxRate->effective_rate, 2);
                            $appliedRate = $fxRate->effective_rate;
                        }
                    }
                }

                // Redirect back to wallet with success info
                $currencySymbol = ($targetCurrency === 'USD') ? '$' : '₦';
                $msg = "Successfully deposited {$currencySymbol}" . number_format($creditedAmount, 2) . " into your " . strtoupper($targetCurrency) . " wallet.";
                if ($appliedRate) {
                    $msg .= " (Rate: 1 USD = ₦" . number_format($appliedRate, 2) . ")";
                }

                $query = 'payment_success=' . $creditedAmount . '&reference=' . $reference . '&currency=' . $targetCurrency . '&message=' . urlencode($msg);
                if ($appliedRate) {
                    $query .= '&fx_rate=' . $appliedRate;
                }
                if ($targetCurrency === 'USD') {
                    $query .= '&ngn_paid=' . $amount;
                }

                return redirect('/wallet?'.$query);
            } else {
                // Payment failed or pending
                Log::warning('[Paystack:redirect] Payment not successful', [
                    'reference' => $reference,
                    'status' => $result['status'],
                ]);

                return redirect('/wallet?payment_error=payment_failed');
            }
        } catch (\Throwable $e) {
            Log::error('[Paystack:redirect] Exception during verification', [
                'reference' => $reference,
                'error' => $e->getMessage(),
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
            'body' => $request->all(),
        ]);

        try {
            // Verify webhook signature
            $signature = $request->header('x-paystack-signature');
            $secret = config('services.paystack.secret_key');

            if (! $this->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
                Log::warning('[Paystack:webhook] Invalid signature', [
                    'signature' => $signature,
                ]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $payload = $request->all();

            // Only process successful payments
            if (($payload['event'] ?? null) === 'charge.success') {
                $data = $payload['data'];
                $reference = $data['reference'];
                $userId = $data['metadata']['user_id'] ?? null;
                $targetCurrency = $data['metadata']['target_currency'] ?? 'NGN';
                $appliedRate = $data['metadata']['fx_rate'] ?? null;

                $amount = $data['amount'] / 100; // Convert from kobo
                $charge = ($data['fees'] ?? 0) / 100; // Get actual Paystack charge
                $netAmount = $amount - $charge;

                Log::info('[Paystack:webhook] Processing charge.success', [
                    'reference' => $reference,
                    'user_id' => $userId,
                    'target_currency' => $targetCurrency,
                    'raw_amount' => $data['amount'] ?? 0,
                    'amount' => $amount,
                    'charge' => $charge,
                    'net_amount' => $netAmount,
                    'metadata' => $data['metadata'] ?? [],
                ]);

                if (! $userId) {
                    Log::error('[Paystack:webhook] No user_id in metadata', [
                        'reference' => $reference,
                        'metadata' => $data['metadata'] ?? [],
                    ]);

                    return response()->json(['error' => 'Invalid metadata'], 400);
                }

                $user = \App\Models\User::find($userId);

                if (! $user) {
                    Log::error('[Paystack:webhook] User not found', [
                        'reference' => $reference,
                        'user_id' => $userId,
                    ]);

                    return response()->json(['error' => 'User not found'], 404);
                }

                // Check if transaction already processed (by webhook or redirect)
                $existingTransaction = NewTransaction::where('meta->reference', $reference)->first();
                if ($existingTransaction) {
                    Log::info('[Paystack:webhook] Transaction already processed', [
                        'reference' => $reference,
                        'existing_transaction_id' => $existingTransaction->id,
                    ]);

                    return response()->json(['message' => 'Already processed'], 200);
                }

                // Apply FX conversion if needed
                $convertedAmount = $netAmount;
                if ($targetCurrency === 'USD') {
                    $usdAmount = $data['metadata']['usd_amount'] ?? null;
                    if ($usdAmount) {
                        $convertedAmount = $usdAmount;
                    } else {
                        $fxRate = FxRate::where('from_currency', 'NGN')
                            ->where('to_currency', 'USD')
                            ->first();
                        if ($fxRate) {
                            $convertedAmount = round($netAmount / $fxRate->effective_rate, 2);
                            $appliedRate = $fxRate->effective_rate;
                        }
                    }
                }

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $userId, 'currency' => $targetCurrency],
                    [
                        'balance' => 0, 
                        'status' => 'active', 
                        'ngn_cleared' => 0, 
                        'ngn_uncleared' => 0, 
                        'usd_cleared' => 0, 
                        'usd_uncleared' => 0
                    ]
                );

                Log::info('[Paystack:webhook] Updating wallet', [
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
                    'currency' => $targetCurrency,
                    'current_balance' => $wallet->balance,
                    'amount_to_add' => $convertedAmount,
                    'charge' => $charge,
                    'fx_rate_applied' => $appliedRate,
                ]);

                $wallet->increment('balance', $convertedAmount);
                if ($targetCurrency === 'NGN') {
                    $wallet->increment('ngn_cleared', $convertedAmount);
                } elseif ($targetCurrency === 'USD') {
                    $wallet->increment('usd_cleared', $convertedAmount);
                }

                Log::info('[Paystack:webhook] Wallet balance updated', [
                    'new_balance' => $wallet->fresh()->balance,
                ]);

                // Record the transaction
                $transaction = NewTransaction::create([
                    'user_id' => $userId,
                    'amount' => $convertedAmount,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'charge' => $charge,
                    'net_amount' => $convertedAmount,
                    'currency' => $targetCurrency,
                    'meta' => [
                        'reference' => $reference,
                        'gateway' => 'paystack',
                        'ngn_amount' => $amount,
                        'fx_rate_applied' => $appliedRate,
                    ],
                ]);

                Log::info('[Paystack:webhook] Transaction created', [
                    'transaction_id' => $transaction->id,
                    'user' => $user->email,
                    'amount' => $amount,
                    'currency' => $targetCurrency,
                    'net_amount' => $convertedAmount,
                    'reference' => $reference,
                    'new_balance' => $wallet->fresh()->balance,
                ]);
            }

            return response()->json(['message' => 'Webhook processed'], 200);
        } catch (\Throwable $e) {
            Log::error('[Paystack:webhook] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        $targetCurrency = $result['metadata']['target_currency'] ?? 'NGN';
        if (! $userId) {
            return response()->json(['success' => false], 400);
        }

        return DB::transaction(function () use ($result, $reference, $userId, $targetCurrency) {
            $existing = NewTransaction::where('meta->reference', $reference)->first();
            if ($existing) {
                return response()->json(['success' => true]);
            }

            // `verifyPayment()` already returns amount in NGN (not kobo)
            $amount = $result['amount'];
            $charge = ($result['fees'] ?? 0) / 100;
            $netAmount = $amount - $charge;

            // If converting to USD, apply FX rate
            $convertedAmount = $netAmount;
            $appliedRate = $result['metadata']['fx_rate'] ?? null;

            if ($targetCurrency === 'USD') {
                $usdAmount = $result['metadata']['usd_amount'] ?? null;
                if ($usdAmount) {
                    $convertedAmount = $usdAmount;
                } else {
                    $fxRate = FxRate::where('from_currency', 'NGN')
                        ->where('to_currency', 'USD')
                        ->first();
                    if ($fxRate) {
                        $convertedAmount = round($netAmount / $fxRate->effective_rate, 2);
                        $appliedRate = $fxRate->effective_rate;
                    }
                }
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'currency' => $targetCurrency],
                [
                    'balance' => 0, 
                    'status' => 'active', 
                    'ngn_cleared' => 0, 
                    'ngn_uncleared' => 0, 
                    'usd_cleared' => 0, 
                    'usd_uncleared' => 0, 
                    'locked' => 0
                ]
            );

            $wallet->increment('balance', $convertedAmount);
            if ($targetCurrency === 'NGN') {
                $wallet->increment('ngn_cleared', $convertedAmount);
            } elseif ($targetCurrency === 'USD') {
                $wallet->increment('usd_cleared', $convertedAmount);
            }

            NewTransaction::create([
                'user_id' => $userId,
                'amount' => $convertedAmount,
                'type' => 'deposit',
                'status' => 'completed',
                'charge' => $charge,
                'net_amount' => $convertedAmount,
                'currency' => $targetCurrency,
                'meta' => [
                    'reference' => $reference,
                    'gateway' => 'paystack',
                    'ngn_amount' => $amount,
                    'fx_rate_applied' => $appliedRate,
                ],
            ]);

            $currencySymbol = ($targetCurrency === 'USD') ? '$' : '₦';
            $successMessage = "Successfully deposited {$currencySymbol}" . number_format($convertedAmount, 2) . " into your " . strtoupper($targetCurrency) . " wallet.";
            if ($appliedRate) {
                $successMessage .= " (Conversion Rate: 1 USD = ₦" . number_format($appliedRate, 2) . ")";
            }

            return response()->json([
                'success' => true,
                'balance' => $wallet->balance,
                'message' => $successMessage
            ]);
        });
    }
}
