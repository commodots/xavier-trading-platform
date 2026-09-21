<?php

namespace App\Services\CSL;

use RuntimeException;

/**
 * Deterministic CSL mock transport.
 *
 * Serves raw provider payloads from tests/Fixtures/CSL so the real CSL
 * service/clients can run end-to-end without credentials. It is only ever
 * consulted through CslClient when services.csl.mock is enabled.
 */
class CslMockTransport
{
    /**
     * CSL endpoint prefix => mock operation.
     */
    protected const ENDPOINT_OPERATIONS = [
        '/GetCRSTMarkets' => 'markets',
        '/GetCRSTInstruments' => 'instruments',
        '/GetCRSTMarketAccounts' => 'market_accounts',
        '/GetCRSTStockPortfolio' => 'portfolio',
        '/GetCRSTSubPortfolios' => 'market_accounts',
        '/GetCRXTMarketStatus' => 'market_status',
        '/GetCRXTMarketStockPrice' => 'quote',
        '/DoCRXTBuyOrder' => 'buy',
        '/DoCRXTSellOrder' => 'sell',
        '/DoCRXTCancelOrder' => 'cancel_request',
        '/DoCRXTUpdateBuyOrder' => 'cancel_request',
        '/DoCRXTUpdateSellOrder' => 'cancel_request',
        '/DoCRXTCreatePriceAlert' => 'cancel_request',
        '/GetCRXTMarketOpenOrders' => 'open_orders',
        '/GetCRXTExecutedOrders' => 'executed_orders',
        '/GetCRXTCancelledOrderByDate' => 'cancelled_order',
    ];

    /**
     * Resolve the mock payload for a CSL endpoint.
     *
     * Returns null when the endpoint has no fixture, so the caller can fall
     * back to the real HTTP transport instead of failing hard.
     */
    public function forEndpoint(string $endpoint): ?array
    {
        $endpoint = '/'.ltrim($endpoint, '/');

        foreach (self::ENDPOINT_OPERATIONS as $prefix => $operation) {
            if ($endpoint === $prefix || str_starts_with($endpoint, $prefix.'/')) {
                return $this->response($operation);
            }
        }

        return null;
    }

    public function response(string $operation, array $payload = []): array
    {
        return match ($operation) {
            'markets' => $this->fixture('markets'),

            'instruments' => $this->fixture('instruments'),

            'market_accounts' => $this->fixture('market_accounts'),

            'portfolio' => $this->fixture('portfolio'),

            'market_status' => $this->fixture('market_status'),

            'quote' => $this->fixture('quote'),

            'buy' => $this->fixture('buy_accepted'),

            'sell' => $this->fixture('sell_accepted'),

            'cancel_request' => $this->fixture('cancel_request_accepted'),

            'open_orders' => $this->fixture('open_orders'),

            'executed_orders' => $this->fixture(
                (string) config('services.csl.mock_executed_fixture', 'partial_fill')
            ),

            'partial_fill' => $this->fixture('partial_fill'),

            'cancelled_order' => $this->fixture('cancelled_order'),

            default => throw new RuntimeException("Unknown CSL mock operation: {$operation}"),
        };
    }

    protected function fixture(string $name): array
    {
        $path = base_path('tests/Fixtures/CSL/'.$name.'.json');

        if (! file_exists($path)) {
            throw new RuntimeException("CSL mock fixture not found: {$name}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Invalid CSL fixture: {$name}");
        }

        return $data;
    }
}
