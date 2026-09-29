<?php

namespace Tests\Feature\CSL;

use App\Models\ProviderAccount;
use App\Models\Symbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\CslFixture;
use Tests\TestCase;

/**
 * csl:diagnose is the single operational health check for the CSL
 * integration, so it is asserted on behaviour rather than output
 * cosmetics: a healthy installation is READY, broken credentials are NOT
 * READY with a non-zero exit code, and a closed market is reported as a
 * status without being treated as a failure.
 */
class CslDiagnoseCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.csl.mock', false);
        config()->set('services.csl.client_id', 'client');
        config()->set('services.csl.client_secret', 'super-secret-value');
        config()->set('services.csl.oauth_url', 'https://csl.test/oauth/token');
        config()->set('services.csl.st_base_url', 'https://csl.test/st');
        config()->set('services.csl.xt_base_url', 'https://csl.test/xt');

        Cache::forget('csl.oauth.access_token');
    }

    public function test_a_fully_prepared_installation_reports_ready(): void
    {
        $this->fakeCsl();
        $this->createMappedAccount();
        $this->createMappedSymbol();

        $command = $this->artisan('csl:diagnose');

        foreach ([
            'CSL DIAGNOSTIC',
            'Configuration',
            'Authentication / API',
            'Provider Account',
            'Instruments',
            'Market',
            'Database',
            'Scheduler',
            'Overall',
            'CSL credentials configured',
            'OAuth authentication',
            'ST API connectivity',
            'XT API connectivity',
            'CSL account mapping',
            'Market account mapped',
            'Trading status',
            'CSL instrument list available',
            'Local CSL symbols mapped',
            'NGX market status',
            'CSL integration status',
            'READY',
        ] as $expected) {
            $command->expectsOutputToContain($expected);
        }

        $command->assertExitCode(0);
    }

    public function test_missing_credentials_fail_the_diagnostic(): void
    {
        /**
         * The historical command could report PASS while trading was
         * impossible, so broken credentials must now fail the run.
         */
        $this->fakeCsl();
        config()->set('services.csl.client_id', null);
        config()->set('services.csl.client_secret', null);

        $this->artisan('csl:diagnose')
            ->expectsOutputToContain('CSL credentials configured')
            ->expectsOutputToContain('OAuth authentication')
            ->expectsOutputToContain('CSL integration status')
            ->expectsOutputToContain('NOT READY')
            ->assertExitCode(1);
    }

    public function test_a_closed_market_is_reported_without_failing(): void
    {
        $this->fakeCsl('CLOSED');
        $this->createMappedAccount();
        $this->createMappedSymbol();

        $this->artisan('csl:diagnose')
            ->expectsOutputToContain('NGX market status')
            ->expectsOutputToContain('CLOSED')
            ->expectsOutputToContain('READY')
            ->assertExitCode(0);
    }

    public function test_secrets_and_tokens_are_never_printed(): void
    {
        $this->fakeCsl();
        $this->createMappedAccount();
        $this->createMappedSymbol();

        $this->artisan('csl:diagnose')
            ->doesntExpectOutputToContain('super-secret-value')
            ->doesntExpectOutputToContain('super-secret-token')
            ->assertExitCode(0);
    }

    public function test_an_unmapped_provider_account_is_reported(): void
    {
        $this->fakeCsl();

        $user = User::factory()->create();

        ProviderAccount::create([
            'user_id' => $user->id,
            'provider' => 'csl',
            'status' => 'inactive',
        ]);

        $this->artisan('csl:diagnose')
            ->expectsOutputToContain('Market account mapped')
            ->expectsOutputToContain('CSL integration status')
            ->expectsOutputToContain('NOT READY')
            ->assertExitCode(1);
    }

    protected function fakeCsl(string $marketStatus = 'OPEN'): void
    {
        Http::fake([
            'https://csl.test/oauth/token' => Http::response([
                'access_token' => 'super-secret-token',
                'expires_in' => 3600,
            ]),
            'https://csl.test/st/GetCRSTInstruments' => Http::response(
                CslFixture::get('instruments')
            ),
            'https://csl.test/st/*' => Http::response(
                CslFixture::get('markets')
            ),
            'https://csl.test/xt/*' => Http::response([
                'GetCRXTMarketStatus' => [[
                    'market_code' => 'NGX',
                    'market_description' => 'Nigerian Exchange',
                    'market_status_code' => $marketStatus,
                ]],
            ]),
        ]);
    }

    protected function createMappedAccount(): void
    {
        $user = User::factory()->create();

        ProviderAccount::create([
            'user_id' => $user->id,
            'provider' => 'csl',
            'customer_id' => 'CUST1',
            'market_id' => 'NGX',
            'product_id' => 'EQUITY',
            'market_account_id' => 'MA1',
            'status' => 'active',
        ]);
    }

    protected function createMappedSymbol(): void
    {
        Symbol::create([
            'symbol' => 'TIP',
            'name' => 'Test Instrument',
            'type' => 'equity',
            'provider' => 'csl',
            'provider_symbol_id' => 'TIP',
            'market_id' => 'NGX',
        ]);
    }
}
