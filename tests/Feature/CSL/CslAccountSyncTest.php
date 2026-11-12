<?php

namespace Tests\Feature\CSL;

use App\Models\CslCustomerProfile;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\CSL\CslAccountService;
use App\Services\CSL\CslStockClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class CslAccountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_status_is_normalized(): void
    {
        $client = Mockery::mock(CslStockClient::class);
        $client->shouldReceive('marketAccounts')->once()->andReturn($this->cslFixture('market_accounts'));

        $account = (new CslAccountService($client))->syncForUser(User::factory()->create()->id, 'CUS-1');

        $this->assertCount(1, $account);
        $this->assertSame('inactive', ProviderAccount::first()->status);
    }

    public function test_hourly_account_sync_includes_approved_customers_without_market_accounts_yet(): void
    {
        $user = User::factory()->create();
        CslCustomerProfile::create([
            'user_id' => $user->id,
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
            'customer_id' => 'CUS-1',
            'status' => 'approved',
        ]);

        $service = Mockery::mock(CslAccountService::class);
        $service->shouldReceive('syncForUser')
            ->once()
            ->with($user->id, 'CUS-1')
            ->andReturn([]);
        $this->app->instance(CslAccountService::class, $service);

        $this->assertSame(0, Artisan::call('csl:sync-account'));
    }
}
