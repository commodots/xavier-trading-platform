<?php

namespace App\Services\CSL;

class CslTradeXClient
{
    public function __construct(
        protected CslClient $client
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    public function buyOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRXTBuyOrder',
            [
                'buyorder' => [$data],
            ]
        );
    }

    public function sellOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRXTSellOrder',
            [
                'sellorder' => [$data],
            ]
        );
    }

    public function cancelOrder(
        string $marketId,
        string $marketAccountId,
        string $orderIdentifier
    ): array {
        /*
         * IMPORTANT:
         *
         * The supplied CSL Swagger spells this field
         * "market_accountid" for the cancel endpoint.
         *
         * Do not change it to market_account_id here.
         */
        return $this->post(
            '/DoCRXTCancelOrder',
            [
                'cancelorder' => [[
                    'market_id' => $marketId,
                    'market_accountid' => $marketAccountId,
                    'order_identifier' => $orderIdentifier,
                ]],
            ]
        );
    }

    public function updateBuyOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRXTUpdateBuyOrder',
            [
                'updatebuyorder' => [$data],
            ]
        );
    }

    public function updateSellOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRXTUpdateSellOrder',
            [
                'updatesellorder' => [$data],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Market
    |--------------------------------------------------------------------------
    */

    public function marketStatus(): array
    {
        return $this->get('/GetCRXTMarketStatus');
    }

    public function marketStatusById(
        string $marketCode
    ): array {
        return $this->get(
            '/GetCRXTMarketStatusByID/'
            .rawurlencode($marketCode)
        );
    }

    public function stockPrice(
        string $symbolCode
    ): array {
        return $this->get(
            '/GetCRXTMarketStockPrice/'
            .rawurlencode($symbolCode)
        );
    }

    public function stockPrices(): array
    {
        return $this->get(
            '/GetCRXTMarketStockPrices'
        );
    }

    public function topBids(
        string $symbolCode
    ): array {
        return $this->get(
            '/GetCRXTMarketTopBids/'
            .rawurlencode($symbolCode)
        );
    }

    public function topOffers(
        string $symbolCode
    ): array {
        return $this->get(
            '/GetCRXTMarketTopOffers/'
            .rawurlencode($symbolCode)
        );
    }

    public function topGainers(): array
    {
        return $this->get(
            '/GetCRXTMarketTopGainers'
        );
    }

    public function topLosers(): array
    {
        return $this->get(
            '/GetCRXTMarketTopLosers'
        );
    }

    public function marketDepthPurchase(): array
    {
        return $this->get(
            '/GetCRXTMarketDepthPurchase'
        );
    }

    public function marketDepthSale(): array
    {
        return $this->get(
            '/GetCRXTMarketDepthSale'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Orders / Execution
    |--------------------------------------------------------------------------
    */

    public function openOrders(
        string $marketAccountId
    ): array {
        return $this->get(
            '/GetCRXTMarketOpenOrders/'
            .rawurlencode($marketAccountId)
        );
    }

    public function executedOrders(
        string $marketAccountId
    ): array {
        return $this->get(
            '/GetCRXTExecutedOrders/'
            .rawurlencode($marketAccountId)
        );
    }

    public function cancelledOrdersByDate(
        string $marketAccountId,
        string $startDate,
        string $endDate
    ): array {
        return $this->get(
            '/GetCRXTCancelledOrderByDate/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($startDate)
            .'/'
            .rawurlencode($endDate)
        );
    }

    public function allOrdersByDate(
        string $marketAccountId,
        string $startDate,
        string $endDate
    ): array {
        return $this->get(
            '/GetCRXTAllOrdersByDate/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($startDate)
            .'/'
            .rawurlencode($endDate)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Price Alerts
    |--------------------------------------------------------------------------
    */

    public function createPriceAlert(
        array $data
    ): array {
        return $this->post(
            '/DoCRXTCreatePriceAlert',
            [
                'createpricealert' => [$data],
            ]
        );
    }

    public function cancelPriceAlert(
        string $marketId,
        string $marketAccountId,
        string $alertNumber
    ): array {
        return $this->post(
            '/DoCRXTCancelPriceAlert',
            [
                'cancelpricealert' => [[
                    'market_id' => $marketId,
                    'market_accountid' => $marketAccountId,
                    'alert_number' => $alertNumber,
                ]],
            ]
        );
    }

    public function priceAlerts(
        string $marketAccountId
    ): array {
        return $this->get(
            '/GetCRXTPriceAlerts/'
            .rawurlencode($marketAccountId)
        );
    }

    public function priceAlert(
        string $marketAccountId,
        string $alertNo
    ): array {
        return $this->get(
            '/GetCRXTPriceAlert/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($alertNo)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP helpers
    |--------------------------------------------------------------------------
    */

    protected function get(
        string $endpoint,
        array $query = []
    ): array {
        return $this->client->json(
            $this->client->xt(
                'GET',
                $endpoint,
                $query
            )
        );
    }

    protected function post(
        string $endpoint,
        array $payload
    ): array {
        return $this->client->json(
            $this->client->orderRequest(
                'POST',
                $endpoint,
                $payload
            )
        );
    }
}
