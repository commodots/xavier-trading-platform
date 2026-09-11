<?php

namespace App\Services\CSL;

class CslStockClient
{
    public function __construct(
        protected CslClient $client
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Markets / Products / Instruments
    |--------------------------------------------------------------------------
    */

    public function markets(): array
    {
        return $this->get('/GetCRSTMarkets');
    }

    public function products(): array
    {
        return $this->get('/GetCRSTProducts');
    }

    public function instruments(): array
    {
        return $this->get('/GetCRSTInstruments');
    }

    public function instrumentPrices(): array
    {
        return $this->get('/GetCRSTInstrumentPrices');
    }

    public function portfolioTypes(): array
    {
        return $this->get('/GetCRSTPortfolioTypes');
    }

    /*
    |--------------------------------------------------------------------------
    | Customer / Accounts
    |--------------------------------------------------------------------------
    */

    public function marketAccounts(
        string $customerId
    ): array {
        return $this->get(
            '/GetCRSTMarketAccounts/'
            .rawurlencode($customerId)
        );
    }

    public function marketAccountDetails(
        string $marketAccountId
    ): array {
        return $this->get(
            '/GetCRSTMarketAccountDetails/'
            .rawurlencode($marketAccountId)
        );
    }

    public function subPortfolios(
        string $subCustomerId
    ): array {
        return $this->get(
            '/GetCRSTSubPortfolios/'
            .rawurlencode($subCustomerId)
        );
    }

    public function subPortfolioDetails(
        string $subPortfolioId
    ): array {
        return $this->get(
            '/GetCRSTSubPortfoliosDetails/'
            .rawurlencode($subPortfolioId)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Portfolio
    |--------------------------------------------------------------------------
    */

    public function stockPortfolio(
        string $marketAccountId
    ): array {
        return $this->get(
            '/GetCRSTStockPortfolio/'
            .rawurlencode($marketAccountId)
        );
    }

    public function stockPositionByPortfolio(
        string $portfolioId
    ): array {
        return $this->get(
            '/GetCRSTStockPositionbyPortfolioID/'
            .rawurlencode($portfolioId)
        );
    }

    public function createPortfolioMarket(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTPortfolioMarket',
            [
                'portfoliomarket' => [$data],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prices
    |--------------------------------------------------------------------------
    */

    public function stockPrice(
        string $symbolId
    ): array {
        return $this->get(
            '/GetCRSTStockPrice/'
            .rawurlencode($symbolId)
        );
    }

    public function stockPrices(
        string $symbolId
    ): array {
        return $this->get(
            '/GetCRSTStockprices/'
            .rawurlencode($symbolId)
        );
    }

    public function stockByDate(
        string $symbolId,
        string $startDate,
        string $endDate
    ): array {
        return $this->get(
            '/GetCRSTStockByDate/'
            .rawurlencode($symbolId)
            .'/'
            .rawurlencode($startDate)
            .'/'
            .rawurlencode($endDate)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    public function buyOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTStockBuyOrder',
            [
                'order' => [$data],
            ]
        );
    }

    public function sellOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTStockSellOrder',
            [
                'order' => [$data],
            ]
        );
    }

    public function buyPreOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTStockBuyPreOrder',
            [
                'preorder' => [$data],
            ]
        );
    }

    public function sellPreOrder(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTStockSellPreOrder',
            [
                'preorder' => [$data],
            ]
        );
    }

    public function activeOrders(
        string $customerId
    ): array {
        return $this->get(
            '/GetCRSTActiveOrders/'
            .rawurlencode($customerId)
        );
    }

    public function ordersByPortfolio(
        string $portfolioId
    ): array {
        return $this->get(
            '/GetCRSTOrders/'
            .rawurlencode($portfolioId)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cancellation
    |--------------------------------------------------------------------------
    */

    public function cancelBuyOrder(
        string $cscsno,
        string $symbolId,
        int $orderNo
    ): array {
        return $this->post(
            '/DoCRSTCancellationBuyOrder',
            [
                'cancelbuyorder' => [[
                    'cscsno' => $cscsno,
                    'symbol_id' => $symbolId,
                    'orderno' => $orderNo,
                ]],
            ]
        );
    }

    public function cancelSellOrder(
        string $cscsno,
        string $symbolId,
        int $orderNo
    ): array {
        return $this->post(
            '/DoCRSTCancellationSellOrder',
            [
                'cancelsellorder' => [[
                    'cscsno' => $cscsno,
                    'symbol_id' => $symbolId,
                    'orderno' => $orderNo,
                ]],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Lien
    |--------------------------------------------------------------------------
    */

    public function stockLien(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTStockLin',
            [
                'StockLin' => [$data],
            ]
        );
    }

    public function cancelStockLien(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTStockCancelLin',
            [
                'StockLin' => [$data],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fee calculators
    |--------------------------------------------------------------------------
    */

    public function equityPurchaseCalculator(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTEquityPurchaseCalculator',
            [
                'PurchaseFeeCalculator' => [$data],
            ]
        );
    }

    public function equitySaleCalculator(
        array $data
    ): array {
        return $this->post(
            '/DoCRSTEquitySaleCalculator',
            [
                'SaleFeeCalculator' => [$data],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Trades
    |--------------------------------------------------------------------------
    */

    public function trades(
        string $customerId
    ): array {
        return $this->get(
            '/GetCRSTTrades/'
            .rawurlencode($customerId)
        );
    }

    public function tradeByDate(
        string $marketAccountId,
        string $startDate,
        string $endDate
    ): array {
        return $this->get(
            '/GetCRSTTradeByDate/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($startDate)
            .'/'
            .rawurlencode($endDate)
        );
    }

    public function tradeByPostedDate(
        string $marketAccountId,
        string $startDate,
        string $endDate
    ): array {
        return $this->get(
            '/GetCRSTTradeByPostDate/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($startDate)
            .'/'
            .rawurlencode($endDate)
        );
    }

    public function tradeByPostedDateWithFee(
        string $marketAccountId,
        string $startDate,
        string $endDate
    ): array {
        return $this->get(
            '/GetCRSTTradeByPostDateWithfee/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($startDate)
            .'/'
            .rawurlencode($endDate)
        );
    }

    public function tradeByCancelledDate(
        string $marketAccountId,
        string $startDate,
        string $endDate
    ): array {
        return $this->get(
            '/GetCRSTTradeByCancelledDate/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($startDate)
            .'/'
            .rawurlencode($endDate)
        );
    }

    public function tradeByFeeNumber(
        string $tradeNumber
    ): array {
        return $this->get(
            '/GetCRSTTradeByFeeNumber/'
            .rawurlencode($tradeNumber)
        );
    }

    public function tradeByNumber(
        string $marketAccountId,
        string $tradeNumber
    ): array {
        return $this->get(
            '/GetTradeByNumber/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($tradeNumber)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Documents / Reports
    |--------------------------------------------------------------------------
    */

    public function contractNote(
        string $marketAccountId,
        string $tradeNo
    ): array {
        return $this->get(
            '/GetCRSTContractNote/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($tradeNo)
        );
    }

    public function valuationReport(
        string $portfolioId,
        string $marketAccountId,
        string $valuationDate
    ): array {
        return $this->get(
            '/GetCRSTValuationReportBase64/'
            .rawurlencode($portfolioId)
            .'/'
            .rawurlencode($marketAccountId)
            .'/'
            .rawurlencode($valuationDate)
        );
    }

    public function certificates(
        string $customerId
    ): array {
        return $this->get(
            '/GetCRSTCertificates/'
            .rawurlencode($customerId)
        );
    }

    public function publicOffers(): array
    {
        return $this->get('/GetCRSTPublicOffers');
    }

    /*
    |--------------------------------------------------------------------------
    | Internal HTTP helpers
    |--------------------------------------------------------------------------
    */

    protected function get(
        string $endpoint,
        array $query = []
    ): array {
        return $this->client->json(
            $this->client->st(
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
            $this->client->st(
                'POST',
                $endpoint,
                $payload
            )
        );
    }
}
