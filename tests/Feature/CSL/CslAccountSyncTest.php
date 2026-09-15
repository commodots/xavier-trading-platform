<?php

namespace Tests\Feature\CSL;

use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\CSL\CslAccountService;
use App\Services\CSL\CslStockClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
