<?php

namespace Tests\Feature\CSL;

use App\Models\KycProfile;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\CSL\CslCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CslIndividualCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_is_submitted_and_customer_id_is_saved(): void
    {
        config()->set('services.csl.mock', false);
        config()->set('services.csl.oauth_url', 'https://csl.test/oauth/token');
        config()->set('services.csl.base_url', 'https://csl.test/symplustest/cslapi/cslzp/xml');
        config()->set('services.csl.client_id', 'client');
        config()->set('services.csl.client_secret', 'secret');

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ]),
            '*/DoCRCSNewIndividualCustomer' => Http::response([
                'status' => 'success',
                'result' => [[
                    'code' => 'A',
                    'remarks' => 'Customer approved',
                    'reference' => [
                        'customer_id' => 'CUS-123',
                        'customer_source_reference' => 'SRC-123',
                    ],
                ]],
            ]),
            '*/GetCRSTMarketAccounts/CUS-123' => Http::response([
                'GetCRSTMarketAccounts' => [[
                    'customer_id' => 'CUS-123',
                    'market_account_id' => 'ACC-123',
                    'trading_status' => 'ACTIVE',
                ]],
            ]),
        ]);

        $user = $this->userWithCslProfile('verified');

        app(CslCustomerService::class)->submitForUser($user->id);

        $profile = $user->cslCustomerProfile()->firstOrFail();

        $this->assertSame('approved', $profile->status);
        $this->assertSame('CUS-123', $profile->customer_id);
        $this->assertSame('approved', $profile->fresh()->status);
        $this->assertDatabaseHas('provider_accounts', [
            'user_id' => $user->id,
            'provider' => 'csl',
            'customer_id' => 'CUS-123',
            'market_account_id' => 'ACC-123',
            'status' => 'active',
        ]);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/DoCRCSNewIndividualCustomer')) {
                return false;
            }

            $customer = $request->data()['customer'][0] ?? [];

            return ($customer['first_name'] ?? null) === 'Test'
                && ($customer['last_name'] ?? null) === 'User'
                && ($customer['other_names'] ?? null) === 'Middle'
                && ($customer['gender_cd'] ?? null) === 'M'
                && ($customer['bank_account_no'] ?? null) === '0123456789'
                && ($customer['bank_bvn_no'] ?? null) === '12345678901';
        });
    }

    public function test_customer_submission_remains_pending_when_csl_has_not_released_id(): void
    {
        config()->set('services.csl.mock', false);
        config()->set('services.csl.oauth_url', 'https://csl.test/oauth/token');
        config()->set('services.csl.base_url', 'https://csl.test/symplustest/cslapi/cslzp/xml');
        config()->set('services.csl.client_id', 'client');
        config()->set('services.csl.client_secret', 'secret');

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ]),
            '*/DoCRCSNewIndividualCustomer' => Http::response([
                'status' => 'success',
                'result' => [[
                    'code' => 'P',
                    'remarks' => 'Awaiting approval',
                    'reference' => [
                        'customer_source_reference' => 'SRC-123',
                    ],
                ]],
            ]),
        ]);

        $user = $this->userWithCslProfile('verified');

        app(CslCustomerService::class)->submitForUser($user->id);

        $profile = $user->cslCustomerProfile()->firstOrFail();

        $this->assertSame('pending_approval', $profile->status);
        $this->assertNull($profile->customer_id);
        $this->assertSame(0, ProviderAccount::query()->count());
    }

    public function test_csl_customer_is_not_submitted_before_kyc_verification(): void
    {
        config()->set('services.csl.mock', false);
        Http::fake();

        $user = $this->userWithCslProfile('pending');

        app(CslCustomerService::class)->submitForUser($user->id);

        $this->assertSame('not_submitted', $user->cslCustomerProfile()->firstOrFail()->status);
        Http::assertNothingSent();
    }

    private function userWithCslProfile(string $kycStatus): User
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Middle User',
            'dob' => '1990-01-01',
        ]);

        $user->cslCustomerProfile()->create([
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        KycProfile::create([
            'user_id' => $user->id,
            'status' => $kycStatus,
        ]);

        return $user;
    }
}
