<?php

namespace Tests\Feature\CSL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CslLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_and_bank_lookup_endpoints_return_csl_codes(): void
    {
        Cache::flush();
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
            '*/GetCRCSCountries' => Http::response([
                'GetCRCSCountries' => [[
                    'COUNTRY_CODE' => 'NG',
                    'COUNTRY_DESCRIPTION' => 'Nigeria',
                    'HOME_YESNO' => 'Y',
                ]],
            ]),
            '*/GetCRCSBanks' => Http::response([
                'GetCRCSBanks' => [[
                    'BANK_CODE' => '001',
                    'BANK_DESCRIPTION' => 'Example Bank',
                    'BANK_TYPE_DESCRIPTION' => 'Commercial',
                    'NIBSS_ID' => '000001',
                ]],
            ]),
        ]);

        $this->getJson('/api/csl/countries')
            ->assertOk()
            ->assertExactJson([
                'countries' => [[
                    'code' => 'NG',
                    'description' => 'Nigeria',
                    'is_home' => true,
                ]],
            ]);

        $this->getJson('/api/csl/banks')
            ->assertOk()
            ->assertExactJson([
                'banks' => [[
                    'code' => '001',
                    'description' => 'Example Bank',
                    'type' => 'Commercial',
                    'nibss_id' => '000001',
                ]],
            ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/GetCRCSCountries'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/GetCRCSBanks'));
    }

    public function test_country_and_bank_lookup_endpoints_use_csl_fixtures_in_mock_mode(): void
    {
        Cache::flush();
        config()->set('services.csl.mock', true);
        Http::fake();

        $this->getJson('/api/csl/countries')
            ->assertOk()
            ->assertJsonPath('countries.0.code', 'NG');

        $this->getJson('/api/csl/banks')
            ->assertOk()
            ->assertJsonPath('banks.0.code', '001');

        Http::assertNothingSent();
    }

    public function test_lookup_endpoints_normalize_live_csl_case_and_output_wrappers(): void
    {
        Cache::flush();
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
            '*/GetCRCSCountries' => Http::response([
                'GetCRCSCountries' => [[
                    'country_code' => 'NGA',
                    'country_description' => 'NIGERIA',
                    'home_yesno' => 1,
                ]],
            ]),
            '*/GetCRCSBanks' => Http::response([
                'output' => [[
                    'bank_code' => 'ACCESS',
                    'bank_description' => 'Access Bank',
                    'bank_type_description' => 'Commercial Bank',
                    'nibss_id' => '044',
                ]],
            ]),
        ]);

        $this->getJson('/api/csl/countries')
            ->assertOk()
            ->assertExactJson([
                'countries' => [[
                    'code' => 'NGA',
                    'description' => 'NIGERIA',
                    'is_home' => true,
                ]],
            ]);

        $this->getJson('/api/csl/banks')
            ->assertOk()
            ->assertExactJson([
                'banks' => [[
                    'code' => 'ACCESS',
                    'description' => 'Access Bank',
                    'type' => 'Commercial Bank',
                    'nibss_id' => '044',
                ]],
            ]);
    }
}
