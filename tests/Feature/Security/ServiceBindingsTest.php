<?php

namespace Tests\Feature\Security;

use App\Services\Stocks\Contracts\MarketDataProvider;
use App\Services\Stocks\Contracts\StockBroker;
use App\Services\Stocks\Mock\MockDriveWealthService;
use App\Services\Stocks\Mock\MockPolygonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceBindingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_services_use_test_mocks_in_testing_environment(): void
    {
        $this->assertInstanceOf(MockDriveWealthService::class, app(StockBroker::class));
        $this->assertInstanceOf(MockPolygonService::class, app(MarketDataProvider::class));
    }
}
