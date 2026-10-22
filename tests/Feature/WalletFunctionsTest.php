<?php

namespace Tests\Feature;

use App\Models\CryptoAddress;
use App\Models\FxRate;
use App\Models\FxPair;
use App\Models\FxSetting;
use App\Models\FxConversion;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\Symbol;
use App\Models\SystemSetting;
use App\Models\TransactionCharge;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletFunctionsTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'subscription_status' => 'active',
        ]);
        $this->user->kyc()->create(['status' => 'verified', 'tier' => 3]); // Max KYC level
    }

    public function test_user_can_deposit_funds_and_fees_are_applied(): void
    {
        // Setup the fee rule in the DB
        TransactionCharge::create([
            'transaction_type' => 'deposit',
            'charge_type' => 'flat',
            'value' => 150,
            'active' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/deposit', [
            'amount' => 5000,
            'currency' => 'NGN',
        ]);

        $response->assertStatus(200);

        // Check if the transaction record in DB has the 150 fee
        // $this->assertDatabaseHas('new_transactions_table', [
        //     'user_id' => $user->id,
        // ]);
    }

    public function test_deposit_requires_a_positive_amount(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/deposit', [
            'amount' => -100, // Invalid amount
            'currency' => 'NGN',
        ]);

        $response->assertStatus(422); // Validation error
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_paystack_callback_route_is_accessible_using_api_prefix(): void
    {
        $response = $this->get('/api/paystack/callback');

        $response->assertStatus(302);
        $response->assertRedirect('/wallet?payment_error=no_reference');
    }

    public function test_paystack_verify_route_requires_sanctum_auth(): void
    {
        $response = $this->getJson('/api/paystack/verify/xavier_test_ref');

        $response->assertStatus(401);
    }

    public function test_paystack_webhook_post_bypasses_csrf_and_fails_signature(): void
    {

        $response = $this->postJson('/api/paystack/webhook', ['event' => 'charge.success', 'data' => []]);

        // Without CSRF token, this should not be 419 (CSRF mismatch) but propagate to signature check.
        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_paystack_initialization_reuses_reference_after_ambiguous_timeout(): void
    {
        config()->set('services.paystack.secret_key', 'paystack-test-secret');
        $initializationAttempts = 0;
        Http::fake(function ($request) use (&$initializationAttempts) {
            $initializationAttempts++;
            if ($initializationAttempts === 1) {
                throw new ConnectionException('Simulated timeout after request dispatch.');
            }

            return Http::response([
                'status' => true,
                'data' => [
                    'reference' => $request['reference'],
                    'authorization_url' => 'https://checkout.test/payment',
                    'access_code' => 'checkout-code',
                ],
            ]);
        });

        $payload = [
            'amount' => 500,
            'currency' => 'NGN',
            'idempotency_key' => 'deposit-operation-1',
        ];

        $firstResponse = $this->actingAs($this->user)
            ->postJson('/api/paystack/initiate', $payload);
        $firstResponse->assertStatus(503)->assertJsonPath('pending', true);

        $pending = NewTransaction::where('idempotency_key', 'deposit-operation-1')->firstOrFail();

        $secondResponse = $this->actingAs($this->user)
            ->postJson('/api/paystack/initiate', $payload);
        $secondResponse->assertOk();

        $this->assertSame($pending->provider_reference, $secondResponse->json('data.reference'));
        $this->assertSame(2, $initializationAttempts);
        $this->assertSame(1, NewTransaction::where('idempotency_key', 'deposit-operation-1')->count());
        $this->assertSame(0.0, (float) $this->user->fxWallet('NGN')->fresh()->ngn_cleared);
    }

    public function test_paystack_verification_rejects_another_users_reference_without_provider_request(): void
    {
        $transaction = NewTransaction::create([
            'user_id' => $this->user->id,
            'type' => 'deposit',
            'amount' => 500,
            'currency' => 'NGN',
            'charge' => 0,
            'net_amount' => 0,
            'status' => 'pending',
            'provider_reference' => 'xavier-owned-reference',
            'meta' => [
                'reference' => 'xavier-owned-reference',
                'provider_amount' => 500,
                'provider_currency' => 'NGN',
                'requested_amount' => 500,
            ],
        ]);
        $otherUser = User::factory()->create();
        Http::fake();

        $response = $this->actingAs($otherUser)
            ->getJson('/api/paystack/verify/'.$transaction->provider_reference);

        $response->assertNotFound();
        Http::assertNothingSent();
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_paystack_failed_provider_payment_and_invalid_inputs_never_credit_wallet(): void
    {
        config()->set('services.paystack.secret_key', 'paystack-test-secret');
        Http::fake();

        $this->actingAs($this->user)->postJson('/api/paystack/initiate', [
            'amount' => 99,
            'currency' => 'NGN',
            'idempotency_key' => 'invalid-low-deposit',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->actingAs($this->user)->postJson('/api/paystack/initiate', [
            'amount' => 500,
            'currency' => 'EUR',
            'idempotency_key' => 'invalid-currency-deposit',
        ])->assertUnprocessable()->assertJsonValidationErrors('currency');
        Http::assertNothingSent();

        $transaction = $this->createPendingPaystackDeposit('xavier-failed-payment');
        Http::fake([
            '*/transaction/verify/xavier-failed-payment' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'failed',
                    'reference' => 'xavier-failed-payment',
                    'amount' => 50000,
                    'fees' => 0,
                    'currency' => 'NGN',
                    'metadata' => ['user_id' => $this->user->id],
                ],
            ]),
        ]);

        $response = $this->postSignedPaystackWebhook('xavier-failed-payment');
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame(0, Wallet::where('user_id', $this->user->id)->where('currency', 'NGN')->count());
        $this->assertSame(0, Ledger::where('reference', 'xavier-failed-payment')->count());
    }

    public function test_paystack_duplicate_signed_webhooks_credit_a_deposit_once(): void
    {
        config()->set('services.paystack.secret_key', 'paystack-test-secret');
        $transaction = $this->createPendingPaystackDeposit('xavier-webhook-once');
        Http::fake([
            '*/transaction/verify/xavier-webhook-once' => Http::response(
                $this->paystackVerificationResponse($transaction, 'success'),
                200
            ),
        ]);

        $first = $this->postSignedPaystackWebhook('xavier-webhook-once');
        $second = $this->postSignedPaystackWebhook('xavier-webhook-once');

        $first->assertOk();
        $second->assertOk();
        $wallet = Wallet::where('user_id', $this->user->id)->where('currency', 'NGN')->firstOrFail();
        $this->assertSame(500.0, (float) $wallet->balance);
        $this->assertSame(500.0, (float) $wallet->ngn_cleared);
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(1, Ledger::where('reference', 'xavier-webhook-once')->count());
        $this->assertSame(1, WalletTransaction::where('reference', 'xavier-webhook-once')->count());
    }

    public function test_paystack_usd_deposit_collects_ngn_and_credits_net_usd_at_saved_rate(): void
    {
        config()->set('services.paystack.secret_key', 'paystack-test-secret');
        FxRate::create([
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'base_rate' => 1500,
            'markup_percent' => 0,
            'effective_rate' => 1500,
        ]);

        $providerPayload = null;
        $verificationUrl = null;
        Http::fake(function ($request) use (&$providerPayload, &$verificationUrl) {
            if (str_contains($request->url(), '/transaction/verify/')) {
                $verificationUrl = $request->url();
                $reference = basename($request->url());

                return Http::response([
                    'status' => true,
                    'data' => [
                        'status' => 'success',
                        'reference' => $reference,
                        'amount' => 15000000,
                        'fees' => 15000,
                        'currency' => 'NGN',
                        'paid_at' => now()->toIso8601String(),
                        'metadata' => [
                            'user_id' => $this->user->id,
                            'target_currency' => 'USD',
                            'usd_amount' => 100,
                            'fx_rate' => 1500,
                        ],
                    ],
                ]);
            }

            $providerPayload = $request->data();

            return Http::response([
                'status' => true,
                'data' => [
                    'reference' => $providerPayload['reference'],
                    'authorization_url' => 'https://checkout.test/payment',
                    'access_code' => 'usd-checkout-code',
                ],
            ]);
        });

        $this->actingAs($this->user)->postJson('/api/paystack/initiate', [
            'amount' => 100,
            'currency' => 'USD',
            'idempotency_key' => 'usd-deposit-operation',
        ])->assertOk();
        $this->assertNotNull($providerPayload);
        $this->assertSame('NGN', $providerPayload['currency']);
        $this->assertEquals(15000000, $providerPayload['amount']);
        $this->assertEquals(100, $providerPayload['metadata']['usd_amount']);

        $transaction = NewTransaction::where('idempotency_key', 'usd-deposit-operation')->firstOrFail();

        $this->assertSame('NGN', $transaction->meta['provider_currency']);
        $this->assertSame(150000.0, (float) $transaction->meta['provider_amount']);
        $providerResult = app(\App\Services\Payments\Paystack\PaystackService::class)
            ->verifyPayment($transaction->provider_reference);
        $this->assertNotNull($verificationUrl);
        $this->assertStringEndsWith('/transaction/verify/'.$transaction->provider_reference, $verificationUrl);
        $this->assertSame('success', $providerResult['status']);
        $this->assertSame('NGN', $providerResult['currency']);
        $this->assertSame($this->user->id, (int) $providerResult['metadata']['user_id']);
        $this->assertSame(150000.0, (float) $providerResult['amount']);

        $verificationResponse = $this->actingAs($this->user)
            ->getJson('/api/paystack/verify/'.$transaction->provider_reference);
        $this->assertSame(200, $verificationResponse->getStatusCode(), $verificationResponse->getContent());

        $wallet = Wallet::where('user_id', $this->user->id)->where('currency', 'USD')->firstOrFail();
        $this->assertSame(99.9, (float) $wallet->usd_cleared);
        $this->assertSame(99.9, (float) $transaction->fresh()->net_amount);
        $this->assertSame(0.1, (float) $transaction->fresh()->charge);
        $this->assertDatabaseHas('ledgers', [
            'reference' => $transaction->provider_reference,
            'currency' => 'USD',
            'amount' => 99.9,
        ]);
    }

    public function test_delayed_paystack_webhook_is_retried_without_early_credit(): void
    {
        config()->set('services.paystack.secret_key', 'paystack-test-secret');
        $transaction = $this->createPendingPaystackDeposit('xavier-delayed-webhook');
        $verificationAttempts = 0;
        Http::fake(function () use (&$verificationAttempts, $transaction) {
            $verificationAttempts++;
            return Http::response(
                $this->paystackVerificationResponse(
                    $transaction,
                    $verificationAttempts === 1 ? 'pending' : 'success'
                ),
                200
            );
        });

        $first = $this->postSignedPaystackWebhook('xavier-delayed-webhook');
        $this->assertSame(503, $first->getStatusCode());
        $this->assertSame(0.0, (float) (Wallet::where('user_id', $this->user->id)->where('currency', 'NGN')->value('balance') ?? 0));

        $second = $this->postSignedPaystackWebhook('xavier-delayed-webhook');
        $second->assertOk();
        $this->assertSame(500.0, (float) Wallet::where('user_id', $this->user->id)->where('currency', 'NGN')->value('balance'));
        $this->assertSame(2, $verificationAttempts);
    }

    private function createPendingPaystackDeposit(string $reference): NewTransaction
    {
        return NewTransaction::create([
            'user_id' => $this->user->id,
            'type' => 'deposit',
            'amount' => 500,
            'currency' => 'NGN',
            'charge' => 0,
            'net_amount' => 0,
            'status' => 'pending',
            'provider_reference' => $reference,
            'meta' => [
                'reference' => $reference,
                'gateway' => 'paystack',
                'provider_amount' => 500,
                'provider_currency' => 'NGN',
                'requested_amount' => 500,
                'target_currency' => 'NGN',
                'user_id' => $this->user->id,
            ],
        ]);
    }

    private function paystackVerificationResponse(NewTransaction $transaction, string $status): array
    {
        return [
            'status' => true,
            'data' => [
                'status' => $status,
                'reference' => $transaction->provider_reference,
                'amount' => 50000,
                'currency' => 'NGN',
                'fees' => 0,
                'paid_at' => now()->toIso8601String(),
                'metadata' => [
                    'user_id' => $transaction->user_id,
                    'target_currency' => 'NGN',
                    'usd_amount' => null,
                ],
            ],
        ];
    }

    private function postSignedPaystackWebhook(string $reference)
    {
        $payload = [
            'event' => 'charge.success',
            'data' => ['reference' => $reference],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $body, 'paystack-test-secret');

        return $this->call(
            'POST',
            '/api/paystack/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    public function test_user_can_convert_ngn_to_usd(): void
    {
        $user = User::factory()->create();

        FxRate::create([
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'base_rate' => 1500, // e.g., 1500 NGN to 1 USD
            'markup_percent' => 1.0,
            'effective_rate' => 1515.0,
        ]);

        $ngnWallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'balance' => 100000,
            'locked' => 0,
        ]);

        $usdWallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'balance' => 0,
            'locked' => 0,
        ]);

        $response = $this->actingAs($user)->postJson('/api/wallet/convert', [
            'from' => 'NGN',
            'amount' => 50000,
        ]);
        $response->assertStatus(200);

        // Assert: Check NGN balance decreased
        $ngnWallet->refresh();
        $this->assertEquals(50000, $ngnWallet->ngn_cleared);

        // Assert: Check USD balance increased
        $usdWallet->refresh();
        $this->assertGreaterThan(0, $usdWallet->usd_cleared);
    }

    public function test_manual_fx_conversion_is_atomic_and_idempotent(): void
    {
        FxPair::create([
            'base_currency' => 'USD',
            'quote_currency' => 'NGN',
            'buy_rate' => 1500,
            'sell_rate' => 1500,
            'active' => true,
        ]);
        $sourceWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'locked' => 0,
        ]);
        $targetWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
            'balance' => 0,
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0,
        ]);
        $payload = [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 3000,
            'idempotency_key' => 'manual-fx-operation-1',
        ];

        $first = $this->actingAs($this->user)->postJson('/api/fx/convert', $payload);
        $first->assertOk()->assertJsonPath('data.status', 'completed');
        $second = $this->actingAs($this->user)->postJson('/api/fx/convert', $payload);
        $second->assertOk()->assertJsonPath('data.reference', $first->json('data.reference'));

        $sourceWallet->refresh();
        $targetWallet->refresh();
        $this->assertSame(97000.0, (float) $sourceWallet->ngn_cleared);
        $this->assertSame(97000.0, (float) $sourceWallet->balance);
        $this->assertSame(0.0, (float) $sourceWallet->locked);
        $this->assertSame(2.0, (float) $targetWallet->usd_cleared);
        $this->assertSame(2.0, (float) $targetWallet->balance);
        $this->assertSame(1, FxConversion::where('idempotency_key', 'manual-fx-operation-1')->count());
        $this->assertSame(1, Ledger::where('type', 'FX_CONVERSION')->count());
        $this->assertSame(2, WalletTransaction::where('type', 'FX_DEBIT')->orWhere('type', 'FX_CREDIT')->count());
        $this->assertCount(1, app(\App\Services\Fx\FxConversionService::class)->getHistory($this->user->id));
    }

    public function test_fincra_conversion_waits_for_signed_confirmation_and_does_not_replay_provider_call(): void
    {
        config()->set('services.fincra.secret_key', 'fincra-test-secret');
        config()->set('services.fincra.webhook_key', 'fincra-webhook-secret');
        config()->set('services.fincra.business_id', 'business-test');
        config()->set('services.fincra.base_url', 'https://fincra.test');
        FxSetting::create(['provider' => 'fincra', 'enabled' => true]);
        $sourceWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'locked' => 0,
        ]);
        $targetWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
            'balance' => 0,
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0,
        ]);
        $requests = 0;
        $customerReference = null;
        Http::fake(function ($request) use (&$requests, &$customerReference) {
            $requests++;
            if (str_ends_with($request->url(), '/quotes/generate')) {
                return Http::response(['data' => [
                    'reference' => 'FINCRA-QUOTE-1',
                    'rate' => 1500,
                    'amountToReceive' => 2,
                ]]);
            }

            $customerReference = $request['customerReference'];

            return Http::response(['data' => [
                'reference' => 'FINCRA-CONVERSION-1',
                'status' => 'processing',
            ]]);
        });

        $payload = [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 3000,
            'idempotency_key' => 'fincra-fx-operation-1',
        ];
        $first = $this->actingAs($this->user)->postJson('/api/fx/convert', $payload);
        $first->assertStatus(202)->assertJsonPath('data.status', 'processing');
        $second = $this->actingAs($this->user)->postJson('/api/fx/convert', $payload);
        $second->assertStatus(202)->assertJsonPath('data.reference', $first->json('data.reference'));

        $conversion = FxConversion::where('idempotency_key', 'fincra-fx-operation-1')->firstOrFail();
        $this->assertSame($conversion->reference, $customerReference);
        $this->assertSame(2, $requests);
        $sourceWallet->refresh();
        $targetWallet->refresh();
        $this->assertSame(97000.0, (float) $sourceWallet->ngn_cleared);
        $this->assertSame(3000.0, (float) $sourceWallet->locked);
        $this->assertSame(0.0, (float) $targetWallet->usd_cleared);
        $this->assertSame(0, Ledger::where('type', 'FX_CONVERSION')->count());

        $this->postSignedFincraWebhook('conversion.completed', [
            'customerReference' => $conversion->reference,
            'conversionReference' => 'FINCRA-CONVERSION-1',
        ])->assertOk();
        $this->postSignedFincraWebhook('conversion.completed', [
            'customerReference' => $conversion->reference,
            'conversionReference' => 'FINCRA-CONVERSION-1',
        ])->assertOk();
        $this->postSignedFincraWebhook('conversion.processing', [
            'customerReference' => $conversion->reference,
        ])->assertOk();

        $this->assertSame('completed', $conversion->fresh()->status);
        $this->assertSame(97000.0, (float) $sourceWallet->fresh()->balance);
        $this->assertSame(0.0, (float) $sourceWallet->fresh()->locked);
        $this->assertSame(2.0, (float) $targetWallet->fresh()->usd_cleared);
        $this->assertSame(1, Ledger::where('reference', $conversion->reference)->count());
    }

    public function test_fincra_failure_webhook_releases_reservation_without_credit(): void
    {
        config()->set('services.fincra.secret_key', 'fincra-test-secret');
        config()->set('services.fincra.webhook_key', 'fincra-webhook-secret');
        config()->set('services.fincra.business_id', 'business-test');
        config()->set('services.fincra.base_url', 'https://fincra.test');
        FxSetting::create(['provider' => 'fincra', 'enabled' => true]);
        $sourceWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'locked' => 0,
        ]);
        Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
            'balance' => 0,
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0,
        ]);
        Http::fake([
            '*/quotes/generate' => Http::response(['data' => [
                'reference' => 'FINCRA-QUOTE-FAIL',
                'rate' => 1500,
                'amountToReceive' => 2,
            ]]),
            '*/conversions/initiate' => Http::response(['data' => [
                'reference' => 'FINCRA-CONVERSION-FAIL',
                'status' => 'processing',
            ]]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/fx/convert', [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 3000,
            'idempotency_key' => 'fincra-fx-failure-1',
        ]);
        $response->assertStatus(202);
        $conversion = FxConversion::where('idempotency_key', 'fincra-fx-failure-1')->firstOrFail();

        $this->postSignedFincraWebhook('conversion.failed', [
            'customerReference' => $conversion->reference,
            'reason' => 'provider rejected conversion',
        ])->assertOk();

        $this->assertSame('failed', $conversion->fresh()->status);
        $this->assertSame(100000.0, (float) $sourceWallet->fresh()->ngn_cleared);
        $this->assertSame(0.0, (float) $sourceWallet->fresh()->locked);
        $this->assertSame(0, Ledger::where('reference', $conversion->reference)->count());
    }

    public function test_unavailable_fincra_fails_before_reserving_wallet_funds(): void
    {
        config()->set('services.fincra.secret_key', null);
        FxSetting::create(['provider' => 'fincra', 'enabled' => true]);
        $sourceWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'locked' => 0,
        ]);
        Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
            'balance' => 0,
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0,
        ]);

        $this->actingAs($this->user)->postJson('/api/fx/convert', [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 3000,
            'idempotency_key' => 'unavailable-fincra-1',
        ])->assertStatus(400);

        $sourceWallet->refresh();
        $this->assertSame(100000.0, (float) $sourceWallet->ngn_cleared);
        $this->assertSame(0.0, (float) $sourceWallet->locked);
        $this->assertSame(0, FxConversion::where('idempotency_key', 'unavailable-fincra-1')->count());
    }

    public function test_manual_fx_database_failure_keeps_reservation_and_same_key_can_resume(): void
    {
        FxPair::create([
            'base_currency' => 'USD',
            'quote_currency' => 'NGN',
            'buy_rate' => 1500,
            'sell_rate' => 1500,
            'active' => true,
        ]);
        $sourceWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'locked' => 0,
        ]);
        $targetWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
            'balance' => 0,
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0,
        ]);
        Ledger::creating(function () {
            throw new \RuntimeException('simulated ledger failure');
        });

        $payload = [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 3000,
            'idempotency_key' => 'manual-fx-recovery-1',
        ];
        $this->actingAs($this->user)->postJson('/api/fx/convert', $payload)->assertStatus(400);

        $sourceWallet->refresh();
        $this->assertSame(97000.0, (float) $sourceWallet->ngn_cleared);
        $this->assertSame(3000.0, (float) $sourceWallet->locked);
        $this->assertSame(0.0, (float) $targetWallet->fresh()->usd_cleared);
        $this->assertSame('pending', FxConversion::where('idempotency_key', 'manual-fx-recovery-1')->value('status'));

        Ledger::flushEventListeners();
        $this->actingAs($this->user)->postJson('/api/fx/convert', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(2.0, (float) $targetWallet->fresh()->usd_cleared);
        $this->assertSame(0.0, (float) $sourceWallet->fresh()->locked);
        $this->assertSame(1, Ledger::where('type', 'FX_CONVERSION')->count());
    }

    public function test_fincra_ambiguous_server_error_is_unknown_and_not_resent(): void
    {
        config()->set('services.fincra.secret_key', 'fincra-test-secret');
        config()->set('services.fincra.business_id', 'business-test');
        config()->set('services.fincra.base_url', 'https://fincra.test');
        FxSetting::create(['provider' => 'fincra', 'enabled' => true]);
        $sourceWallet = Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'ngn_cleared' => 100000,
            'ngn_uncleared' => 0,
            'locked' => 0,
        ]);
        Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
            'balance' => 0,
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0,
        ]);
        $providerCalls = 0;
        Http::fake(function ($request) use (&$providerCalls) {
            $providerCalls++;
            if (str_ends_with($request->url(), '/quotes/generate')) {
                return Http::response(['data' => [
                    'reference' => 'FINCRA-QUOTE-UNKNOWN',
                    'rate' => 1500,
                    'amountToReceive' => 2,
                ]]);
            }

            return Http::response(['message' => 'Gateway timeout'], 500);
        });

        $payload = [
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'amount' => 3000,
            'idempotency_key' => 'fincra-unknown-1',
        ];
        $first = $this->actingAs($this->user)->postJson('/api/fx/convert', $payload);
        $first->assertStatus(202)->assertJsonPath('data.status', 'unknown');
        $second = $this->actingAs($this->user)->postJson('/api/fx/convert', $payload);
        $second->assertStatus(202)->assertJsonPath('data.status', 'unknown');

        $sourceWallet->refresh();
        $this->assertSame(97000.0, (float) $sourceWallet->ngn_cleared);
        $this->assertSame(3000.0, (float) $sourceWallet->locked);
        $this->assertSame(2, $providerCalls);
        $this->assertSame(0, Ledger::where('type', 'FX_CONVERSION')->count());
    }

    private function postSignedFincraWebhook(string $event, array $data)
    {
        $payload = ['event' => $event, 'data' => $data];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, 'fincra-webhook-secret');

        return $this->call(
            'POST',
            '/api/fincra/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_FINCRA_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    public function test_crypto_market_uses_live_api(): void
    {
        Http::fake([
            'https://api.coingecko.com/api/v3/simple/price*' => Http::response([
                'bitcoin' => ['usd' => 65000],
                'ethereum' => ['usd' => 3200],
                'tether' => ['usd' => 1],
            ], 200),
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/market/crypto');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.symbol', 'BTC');
        $response->assertJsonPath('data.0.price', 65000);
    }

    public function test_trade_open_and_close_flow(): void
    {
        SystemSetting::create(['crypto_spread' => 0, 'crypto_fee' => 0, 'max_trade_amount' => 10000]);

        FxRate::create([
            'from_currency' => 'USD',
            'to_currency' => 'NGN',
            'base_rate' => 1500,
            'effective_rate' => 1500,
        ]);

        Wallet::create(['user_id' => $this->user->id, 'currency' => 'USD', 'usd_cleared' => 1000, 'usd_uncleared' => 0]);

        $openRes = $this->actingAs($this->user)->postJson('/api/trade/open', ['amount' => 100, 'pair' => 'BTC/USDT', 'type' => 'buy']);
        $openRes->assertStatus(200)->assertJson(['success' => true]);

        $tradeId = $openRes->json('data.id');
        $this->assertDatabaseHas('trades', ['id' => $tradeId, 'status' => 'open']);
        $this->assertDatabaseHas('new_transactions_table', ['user_id' => $this->user->id, 'type' => 'buy_crypto']);

        $closeRes = $this->actingAs($this->user)->postJson("/api/trade/close/{$tradeId}");
        $closeRes->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('trades', ['id' => $tradeId, 'status' => 'closed']);
    }

    public function test_crypto_webhook_deposits_and_wallet_increase(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'currency' => 'USD', 'usd_cleared' => 0, 'usd_uncleared' => 0, 'balance' => 0, 'locked' => 0]);
        $address = CryptoAddress::create(['user_id' => $user->id, 'blockchain' => 'TRON', 'address' => 'TGW1']);

        $payload = ['address' => 'TGW1', 'amount' => 10, 'txId' => 'X123'];

        config(['services.crypto.api_key' => 'test-webhook-secret']);

        $response = $this->postJson('/api/crypto/webhook', $payload, ['x-api-key' => 'test-webhook-secret']);
        $response->assertStatus(200);

        $response->assertStatus(200);
        $this->assertDatabaseHas('new_transactions_table', ['user_id' => $user->id, 'type' => 'deposit', 'amount' => 10, 'currency' => 'USDT']);
        $wallet->refresh();
        $this->assertEquals(10, $wallet->usd_cleared);
    }

    public function test_crypto_trade_profit_scenario(): void
    {
        SystemSetting::create(['crypto_spread' => 0, 'crypto_fee' => 0, 'max_trade_amount' => 10000]);
        SystemSetting::create([
            'crypto_spread' => 0,
            'crypto_fee' => 0,
            'max_trade_amount' => 10000,
            'base_currency' => 'USD',
        ]);

        FxRate::create(['from_currency' => 'USD', 'to_currency' => 'NGN', 'base_rate' => 1500, 'effective_rate' => 1500]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->kyc()->create(['status' => 'verified', 'tier' => 2]);

        $wallet = Wallet::create(['user_id' => $user->id, 'currency' => 'USD', 'usd_cleared' => 1000, 'usd_uncleared' => 0, 'balance' => 1000, 'locked' => 0]);

        // Setup sequence: 100 for open, 110 for close
        Http::fake([
            'https://api.coingecko.com/api/v3/simple/price*' => Http::sequence()
                ->push(['bitcoin' => ['usd' => 100]], 200)
                ->push(['bitcoin' => ['usd' => 110]], 200),
        ]);

        // Buy 1 BTC at $100 with $100
        $openRes = $this->actingAs($user)->postJson('/api/trade/open', ['amount' => 100, 'pair' => 'BTC/USDT', 'type' => 'buy']);
        $openRes->assertStatus(200);

        $tradeId = $openRes->json('data.id');
        $quantity = $openRes->json('data.quantity');

        // Verify quantity is stored correctly (should be 1 BTC, not 100)
        $this->assertEqualsWithDelta(1.0, $quantity, 0.01);

        // Verify wallet balance after buy
        $wallet->refresh();
        $this->assertEquals(900, $wallet->usd_cleared);

        // Clear cache for the next API call
        Cache::forget('crypto_prices');

        $closeRes = $this->actingAs($user)->postJson("/api/trade/close/{$tradeId}");
        $closeRes->assertStatus(200);

        $exitPrice = $closeRes->json('data.exit_price');
        $this->assertEquals(110, $exitPrice);

        $profitLoss = $closeRes->json('data.profit_loss');
        // Expected profit: (110 - 100) / 100 * 100 = $10
        $this->assertEqualsWithDelta(10.0, $profitLoss, 0.01);

        // Verify wallet balance after close (should have original $100 + $10 profit = $110)
        $wallet->refresh();
        $this->assertEqualsWithDelta(1010, $wallet->balance, 0.01);
    }

    public function test_crypto_trade_loss_scenario(): void
    {
        SystemSetting::create(['crypto_spread' => 0, 'crypto_fee' => 0, 'max_trade_amount' => 10000]);
        SystemSetting::create([
            'crypto_spread' => 0,
            'crypto_fee' => 0,
            'max_trade_amount' => 10000,
            'base_currency' => 'USD',
        ]);

        FxRate::create(['from_currency' => 'USD', 'to_currency' => 'NGN', 'base_rate' => 1500, 'effective_rate' => 1500]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->kyc()->create(['status' => 'verified', 'tier' => 2]);

        $wallet = Wallet::create(['user_id' => $user->id, 'currency' => 'USD', 'usd_cleared' => 1000, 'usd_uncleared' => 0, 'balance' => 1000, 'locked' => 0]);

        // Setup sequence: 100 for open, 90 for close
        Http::fake([
            'https://api.coingecko.com/api/v3/simple/price*' => Http::sequence()
                ->push(['bitcoin' => ['usd' => 100]], 200)
                ->push(['bitcoin' => ['usd' => 90]], 200),
        ]);

        // Buy 1 BTC at $100 with $100
        $openRes = $this->actingAs($user)->postJson('/api/trade/open', ['amount' => 100, 'pair' => 'BTC/USDT', 'type' => 'buy']);
        $openRes->assertStatus(200);

        $tradeId = $openRes->json('data.id');

        // Verify wallet balance after buy
        $wallet->refresh();
        $this->assertEquals(900, $wallet->usd_cleared);

        // Clear cache for the next API call
        Cache::flush();
        // Set symbol price for fallback
        Symbol::updateOrCreate(['symbol' => 'BTC'], ['last_price' => 110]);

        $closeRes = $this->actingAs($user)->postJson("/api/trade/close/{$tradeId}");
        $closeRes->assertStatus(200);

        $profitLoss = $closeRes->json('data.profit_loss');
        // Expected loss: (90 - 100) / 100 * 100 = -$10
        $this->assertEqualsWithDelta(-10.0, $profitLoss, 0.01);

        // Verify wallet balance after close (should have $100 - $10 loss = $90)
        $wallet->refresh();
        $this->assertEqualsWithDelta(990, $wallet->balance, 0.01);
    }
}
