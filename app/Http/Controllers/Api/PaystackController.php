<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoWallet;
use App\Models\FxRate;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\User;
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
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $user = Auth::user();
        $reference = 'xavier_'.uniqid();
        $models = $this->resolveModels($user);
        $targetCurrency = $request->input('currency', 'NGN');
        $providerCurrency = $targetCurrency === 'USD' ? 'NGN' : $targetCurrency;

        // The service handles conversion to kobo, so we pass the base amount.
        $paystackAmount = $request->amount;

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
            $metadata['ngn_amount'] = $paystackAmount;
        }

        // If in Demo mode, bypass Paystack and fund the demo wallet instantly
        if ($models->isDemo) {
            return DB::transaction(function () use ($user, $request, $reference, $models, $targetCurrency) {
                $initData = [
                    'balance' => 0,
                    'status' => 'active',
                    'ngn_cleared' => 0,
                    'usd_cleared' => 0,
                ];

                $wallet = $models->wallet->firstOrCreate(
                    ['user_id' => $user->id, 'currency' => $targetCurrency],
                    $initData
                );

                $oldBalance = $wallet->balance;

                $wallet->increment('balance', $request->amount);
                if ($targetCurrency === 'NGN') {
                    $wallet->increment('ngn_cleared', $request->amount);
                } elseif ($targetCurrency === 'USD') {
                    $wallet->increment('usd_cleared', $request->amount);
                }

                $newBalance = $wallet->fresh()->balance;

                $models->transaction->create([
                    'user_id' => $user->id,
                    'amount' => $request->amount,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'charge' => 0,
                    'net_amount' => $request->amount,
                    'currency' => $targetCurrency,
                    'meta' => [
                        'reference' => $reference,
                        'gateway' => 'demo_instant',
                        'mode' => 'demo',
                        'old_balance' => $oldBalance,
                        'new_balance' => $newBalance,
                    ],
                ]);

                $currencySymbol = ($targetCurrency === 'USD') ? '$' : '₦';

                return response()->json([
                    'success' => true,
                    'is_demo' => true,
                    'message' => "Demo account instantly funded! Successfully deposited {$currencySymbol}".number_format($request->amount, 2).' into your '.strtoupper($targetCurrency).' wallet.',
                    'data' => ['reference' => $reference, 'authorization_url' => null],
                ]);
            });
        }

        $idempotencyKey = $request->input('idempotency_key');
        $pendingTransaction = $idempotencyKey
            ? NewTransaction::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first()
            : null;

        if ($pendingTransaction) {
            if (
                (float) $pendingTransaction->meta['requested_amount'] !== (float) $request->amount ||
                $pendingTransaction->currency !== $targetCurrency
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'This idempotency key was already used for a different deposit.',
                ], 409);
            }

            if ($pendingTransaction->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'already_processed' => true,
                    'message' => 'Deposit already processed.',
                    'data' => ['reference' => $pendingTransaction->provider_reference],
                ]);
            }

            if ($pendingTransaction->status === 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'This deposit attempt failed. Start a new attempt with a new idempotency key.',
                ], 409);
            }

            $reference = $pendingTransaction->provider_reference;
            $metadata = $pendingTransaction->meta;

            if (! empty($metadata['authorization_url'])) {
                return response()->json([
                    'success' => true,
                    'is_demo' => false,
                    'data' => [
                        'reference' => $reference,
                        'authorization_url' => $metadata['authorization_url'],
                        'access_code' => $metadata['access_code'] ?? null,
                    ],
                ]);
            }
        } else {
            $reference = 'xavier_'.bin2hex(random_bytes(16));
            $metadata['provider_amount'] = $paystackAmount;
            $metadata['provider_currency'] = $providerCurrency;
            $pendingTransaction = NewTransaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'currency' => $targetCurrency,
                'charge' => 0,
                'net_amount' => 0,
                'status' => 'pending',
                'provider_reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'meta' => array_merge($metadata, [
                    'reference' => $reference,
                    'gateway' => 'paystack',
                    'requested_amount' => (float) $request->amount,
                    'idempotency_key' => $idempotencyKey,
                ]),
            ]);
            $metadata = $pendingTransaction->meta;
        }

        try {
            $result = $this->paystackService->initializePayment([
                'email' => $user->email,
                'amount' => $paystackAmount,
                'currency' => $providerCurrency,
                'reference' => $reference,
                'callback_url' => route('paystack.callback'),
                'metadata' => $metadata,
            ]);

            if ($result['status'] === 'success') {
                $pendingTransaction->update([
                    'meta' => array_merge($pendingTransaction->meta ?? [], [
                        'authorization_url' => $result['authorization_url'] ?? null,
                        'access_code' => $result['access_code'] ?? null,
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'is_demo' => false,
                    'data' => $result,
                    'fx_details' => ($targetCurrency === 'USD') ? [
                        'rate' => $fxRate->effective_rate,
                        'ngn_total' => $paystackAmount / 100,
                    ] : null,
                ]);
            } elseif (($result['status'] ?? null) === 'uncertain') {
                $pendingTransaction->update([
                    'meta' => array_merge($pendingTransaction->meta ?? [], [
                        'initialization_uncertain' => true,
                    ]),
                ]);

                return response()->json([
                    'success' => false,
                    'pending' => true,
                    'message' => 'Payment initialization status is uncertain. Retry with the same idempotency key.',
                    'reference' => $reference,
                ], 503);
            } else {
                $pendingTransaction->update(['status' => 'failed']);

                return response()->json([
                    'success' => false,
                    'is_demo' => false,
                    'message' => $result['message'] ?? 'Payment initialization failed.',
                    'data' => $result,
                ], 400);
            }
        } catch (\Throwable $e) {
            Log::error('Paystack:initiate exception', ['error' => $e->getMessage()]);

            $pendingTransaction->update([
                'meta' => array_merge($pendingTransaction->meta ?? [], [
                    'initialization_uncertain' => true,
                ]),
            ]);

            return response()->json([
                'success' => false,
                'pending' => true,
                'message' => 'Payment initialization status is uncertain. Retry with the same idempotency key.',
                'reference' => $reference,
            ], 503);
        }
    }

    public function verify($reference)
    {
        $transaction = NewTransaction::query()
            ->where('provider_reference', $reference)
            ->orWhere('meta->reference', $reference)
            ->first();

        if (! $transaction || $transaction->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Payment reference not found.'], 404);
        }

        if ($transaction->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Already processed',
                'balance' => Wallet::where('user_id', $transaction->user_id)->where('currency', $transaction->currency)->value('balance'),
            ]);
        }

        if ($transaction->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Payment is not awaiting verification.'], 409);
        }

        try {
            $result = $this->paystackService->verifyPayment($reference);
            $expectedCurrency = $transaction->meta['provider_currency'] ?? $transaction->currency;
            $expectedAmount = (float) ($transaction->meta['provider_amount'] ?? $transaction->amount);

            if (
                ($result['status'] ?? null) === 'success' &&
                ($result['reference'] ?? null) === $reference &&
                (int) ($result['metadata']['user_id'] ?? 0) === (int) $transaction->user_id &&
                strtoupper((string) ($result['currency'] ?? '')) === strtoupper($expectedCurrency) &&
                abs((float) ($result['amount'] ?? 0) - $expectedAmount) < 0.01
            ) {
                return $this->processSuccessfulPayment($result, $reference);
            }

            return response()->json(['success' => false, 'message' => 'Verified payment details do not match this deposit.', 'data' => null], 400);
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

        $transaction = NewTransaction::query()
            ->where('provider_reference', $reference)
            ->orWhere('meta->reference', $reference)
            ->first();

        if (! Auth::check() || ! $transaction || (int) $transaction->user_id !== (int) Auth::id()) {
            return redirect('/wallet?payment_error=invalid_reference');
        }

        try {
            // Verify the payment status
            $result = $this->paystackService->verifyPayment($reference);

            $expectedCurrency = $transaction->meta['provider_currency'] ?? $transaction->currency;
            $expectedAmount = (float) ($transaction->meta['provider_amount'] ?? $transaction->amount);
            $matchesPendingPayment = ($result['status'] ?? null) === 'success'
                && ($result['reference'] ?? null) === $reference
                && (int) ($result['metadata']['user_id'] ?? 0) === (int) $transaction->user_id
                && strtoupper((string) ($result['currency'] ?? '')) === strtoupper($expectedCurrency)
                && abs((float) ($result['amount'] ?? 0) - $expectedAmount) < 0.01;

            if ($matchesPendingPayment) {
                $processed = $this->processSuccessfulPayment($result, $reference);
                if ($processed->getStatusCode() >= 400) {
                    return redirect('/wallet?payment_error=verification_error');
                }

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
                $msg = "Successfully deposited {$currencySymbol}".number_format($creditedAmount, 2).' into your '.strtoupper($targetCurrency).' wallet.';
                if ($appliedRate) {
                    $msg .= ' (Rate: 1 USD = ₦'.number_format($appliedRate, 2).')';
                }

                $query = 'payment_success='.$creditedAmount.'&reference='.$reference.'&currency='.$targetCurrency.'&message='.urlencode($msg);
                if ($appliedRate) {
                    $query .= '&fx_rate='.$appliedRate;
                }
                if ($targetCurrency === 'USD') {
                    $query .= '&ngn_paid='.$amount;
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
        $signature = $request->header('x-paystack-signature');
        $secret = config('services.paystack.secret_key');

        if (! $this->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
            Log::warning('[Paystack:webhook] Invalid signature', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        if (($payload['event'] ?? null) !== 'charge.success') {
            return response()->json(['status' => 'ignored']);
        }

        $reference = $payload['data']['reference'] ?? null;
        if (! is_string($reference) || $reference === '') {
            return response()->json(['error' => 'Missing payment reference'], 400);
        }

        $result = $this->paystackService->verifyPayment($reference);
        if (($result['status'] ?? null) !== 'success' || ($result['reference'] ?? null) !== $reference) {
            return response()->json(['error' => 'Payment is not verified yet'], 503);
        }

        return $this->processSuccessfulPayment($result, $reference);
    }

    /**
     * Verify Paystack webhook signature
     */
    private function verifyWebhookSignature(string $payload, ?string $signature, ?string $secret): bool
    {
        if (! $signature || ! $secret) {
            return false;
        }

        $computedSignature = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computedSignature, $signature);
    }

    private function processSuccessfulPayment(array $result, string $reference)
    {
        $userId = (int) ($result['metadata']['user_id'] ?? 0);
        $providerReference = $result['reference'] ?? null;

        if (! $userId || $providerReference !== $reference) {
            return response()->json(['success' => false, 'message' => 'Invalid provider payment details.'], 400);
        }

        return DB::transaction(function () use ($result, $reference, $userId) {
            $transaction = NewTransaction::query()
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $transaction || (int) $transaction->user_id !== $userId) {
                return response()->json(['success' => false, 'message' => 'Deposit transaction not found.'], 404);
            }

            if ($transaction->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'already_processed' => true,
                    'balance' => Wallet::where('user_id', $userId)->where('currency', $transaction->currency)->value('balance'),
                ]);
            }

            if ($transaction->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Deposit is not pending.'], 409);
            }

            $targetCurrency = $transaction->currency;
            $expectedCurrency = $transaction->meta['provider_currency'] ?? $targetCurrency;
            $providerAmount = (float) ($result['amount'] ?? 0);
            $expectedAmount = (float) ($transaction->meta['provider_amount'] ?? $transaction->amount);

            if (
                strtoupper((string) ($result['currency'] ?? '')) !== strtoupper($expectedCurrency) ||
                abs($providerAmount - $expectedAmount) >= 0.01
            ) {
                return response()->json(['success' => false, 'message' => 'Provider payment does not match the pending deposit.'], 400);
            }

            $providerCharge = min($providerAmount, max(0, (float) ($result['fees'] ?? 0) / 100));
            $appliedRate = (float) ($transaction->meta['fx_rate'] ?? 0);
            if ($targetCurrency === 'USD' && ($expectedCurrency !== 'NGN' || $appliedRate <= 0)) {
                return response()->json(['success' => false, 'message' => 'The USD deposit FX rate is invalid.'], 400);
            }

            $creditedAmount = $targetCurrency === 'USD'
                ? round(($providerAmount - $providerCharge) / $appliedRate, 2)
                : $providerAmount - $providerCharge;
            $targetCharge = $targetCurrency === 'USD'
                ? round($providerCharge / $appliedRate, 2)
                : $providerCharge;

            if ($creditedAmount <= 0) {
                return response()->json(['success' => false, 'message' => 'Provider returned an invalid deposit amount.'], 400);
            }

            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->where('currency', $targetCurrency)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'currency' => $targetCurrency,
                    'balance' => 0,
                    'status' => 'active',
                    'ngn_cleared' => 0,
                    'ngn_uncleared' => 0,
                    'usd_cleared' => 0,
                    'usd_uncleared' => 0,
                    'locked' => 0,
                ]);
            }

            $oldBalance = (float) $wallet->balance;
            $clearedColumn = $targetCurrency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $wallet->increment('balance', $creditedAmount);
            $wallet->increment($clearedColumn, $creditedAmount);
            $wallet->refresh();

            $meta = array_merge($transaction->meta ?? [], [
                'reference' => $reference,
                'gateway' => 'paystack',
                'provider_amount' => $providerAmount,
                'provider_currency' => $expectedCurrency,
                'provider_charge' => $providerCharge,
                'fx_rate_applied' => $appliedRate ?: null,
                'old_balance' => $oldBalance,
                'new_balance' => (float) $wallet->balance,
                'paid_at' => $result['paid_at'] ?? null,
            ]);

            $transaction->update([
                'amount' => $creditedAmount,
                'charge' => $targetCharge,
                'net_amount' => $creditedAmount,
                'status' => 'completed',
                'meta' => $meta,
            ]);

            Ledger::firstOrCreate(
                ['reference' => $reference],
                [
                    'user_id' => $userId,
                    'currency' => $targetCurrency,
                    'amount' => $creditedAmount,
                    'type' => 'FUND',
                    'status' => 'completed',
                    'meta' => $meta,
                    'is_platform' => false,
                ]
            );

            \App\Models\WalletTransaction::firstOrCreate(
                ['reference' => $reference, 'user_id' => $userId],
                [
                    'wallet_currency' => $targetCurrency,
                    'type' => 'deposit',
                    'amount' => $creditedAmount,
                    'note' => 'Paystack deposit',
                ]
            );

            return response()->json([
                'success' => true,
                'balance' => (float) $wallet->balance,
                'message' => 'Deposit verified and credited.',
            ]);
        });
    }
}
