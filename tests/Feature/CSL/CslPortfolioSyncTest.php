<?php

namespace Tests\Feature\CSL;

use App\Models\Portfolio;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\CSL\CslPortfolioSyncService;
use App\Services\CSL\CslStockClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CslPortfolioSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_portfolio_fields_are_mapped(): void
    {
        $user = User::factory()->create();
        $account = ProviderAccount::create([
            'user_id' => $user->id,
            'provider' => 'csl',
            'market_account_id' => 'ACC-1',
            'status' => 'active',
        ]);
        $client = Mockery::mock(CslStockClient::class);
        $client->shouldReceive('stockPortfolio')->once()->with('ACC-1')->andReturn([
            'GetStockPortfolio' => [[
                'symbol_identifier' => '42',
                'symbol_description' => 'Test Industries',
                'unit_quantity' => '10',
                'average_cost_price' => '100.50',
                'market_price' => '110.25',
            ]],
        ]);

        $count = (new CslPortfolioSyncService($client))->sync($account);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('portfolios', [
            'user_id' => $user->id,
            'symbol' => '42',
            'quantity' => 10,
            'avg_price' => 100.5,
            'market_price' => 110.25,
        ]);
    }

    public function test_portfolio_sync_preserves_locally_uncleared_quantity(): void
    {
        $user = User::factory()->create();
        $account = ProviderAccount::create([
            'user_id' => $user->id,
            'provider' => 'csl',
            'market_account_id' => 'ACC-2',
            'status' => 'active',
        ]);
        $portfolio = Portfolio::create([
            'user_id' => $user->id,
            'symbol' => '42',
            'name' => 'Test Industries',
            'category' => 'local',
            'currency' => 'NGN',
            'quantity' => 10,
            'cleared_quantity' => 0,
            'uncleared_quantity' => 10,
            'avg_price' => 100,
            'market_price' => 100,
        ]);
        $client = Mockery::mock(CslStockClient::class);
        $client->shouldReceive('stockPortfolio')->once()->with('ACC-2')->andReturn([
            'GetStockPortfolio' => [[
                'symbol_identifier' => '42',
                'symbol_description' => 'Test Industries',
                'unit_quantity' => '10',
                'average_cost_price' => '100',
                'market_price' => '105',
            ]],
        ]);

        (new CslPortfolioSyncService($client))->sync($account);

        $portfolio->refresh();
        $this->assertSame(10.0, (float) $portfolio->quantity);
        $this->assertSame(0.0, (float) $portfolio->cleared_quantity);
        $this->assertSame(10.0, (float) $portfolio->uncleared_quantity);
    }
}
