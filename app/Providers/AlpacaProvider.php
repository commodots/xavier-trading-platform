<?php

namespace App\Providers;

use App\Services\Stocks\Exceptions\ProviderRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AlpacaProvider
{
    /** @var array<string, array<string, mixed>> */
    protected static array $mockOrders = [];

    protected $key;

    protected $secret;

    protected $baseUrl;

    protected $dataBaseUrl;

    protected int $timeout;

    public function __construct()
    {
        // Read from config() (not env()) so config:cache works and tests can
        // override values with config()->set().
        $this->key = config('services.alpaca.api_key');
        $this->secret = config('services.alpaca.secret_key');
        $this->baseUrl = rtrim(
            (string) config(
                'services.alpaca.base_url',
                'https://paper-api.alpaca.markets'
            ),
            '/'
        );
        $this->dataBaseUrl = rtrim(
            (string) config(
                'services.alpaca.data_base_url',
                'https://data.alpaca.markets/v2'
            ),
            '/'
        );
        $this->timeout = (int) config('services.alpaca.timeout', 15);
    }

    public function quote(string $symbol): float
    {
        if ($this->isMockEnabled()) {
            return 100.0;
        }

        if (! $this->credentialsConfigured()) {
            return 0.0;
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get($this->dataBaseUrl.'/stocks/'.strtoupper($symbol).'/latest/quote');

            if ($response->successful()) {
                $data = $response->json();

                return (float) ($data['quote']['ap'] ?? $data['quote']['bp'] ?? 0.0);
            }

            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function quoteDetails(string $symbol): array
    {
        if ($this->isMockEnabled()) {
            return [
                'symbol' => strtoupper($symbol),
                'name' => strtoupper($symbol),
                'price' => 100.0,
                'previous_close' => 99.0,
                'change' => 1.0,
                'timestamp' => now()->toISOString(),
            ];
        }

        if (! $this->credentialsConfigured()) {
            return [
                'symbol' => strtoupper($symbol),
                'price' => 0.0,
                'previous_close' => 0.0,
                'change' => 0.0,
                'timestamp' => now()->toISOString(),
            ];
        }

        try {
            $data = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get($this->dataBaseUrl.'/stocks/'.strtoupper($symbol).'/latest/quote')
                ->json();

            $current = (float) ($data['quote']['ap'] ?? $data['quote']['lp'] ?? 0.0);
            $previousClose = $this->getPreviousClose($symbol);
            $change = $previousClose > 0 ? round((($current - $previousClose) / $previousClose) * 100, 2) : 0.0;

            return [
                'symbol' => strtoupper($symbol),
                'price' => $current,
                'previous_close' => $previousClose,
                'change' => $change,
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'symbol' => strtoupper($symbol),
                'price' => 0.0,
                'previous_close' => 0.0,
                'change' => 0.0,
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    protected function getPreviousClose(string $symbol): float
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get($this->dataBaseUrl.'/stocks/'.strtoupper($symbol).'/bars', [
                    'timeframe' => '1Day',
                    'start' => now()->subDays(7)->toDateString(),
                    'end' => now()->toDateString(),
                    'limit' => 2,
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                $bars = $payload['bars'] ?? $payload;
                if (is_array($bars) && count($bars) > 0) {
                    $latest = end($bars);

                    return (float) ($latest['c'] ?? 0.0);
                }
            }
        } catch (\Exception $e) {
        }

        return 0.0;
    }

    /**
     * Historical bars for Global symbols, consumed by AlpacaMarketDataProvider.
     *
     * Returns the raw Alpaca data envelope ({"bars": [...]}) so the market data
     * layer owns the normalisation. Without credentials there is nothing to
     * fetch, so an empty envelope is returned rather than an error.
     */
    public function bars(string $symbol, int $days = 30, string $timeframe = '1Day'): array
    {
        if ($this->isMockEnabled()) {
            return ['bars' => []];
        }

        if (! $this->credentialsConfigured()) {
            throw new RuntimeException('Alpaca credentials are not configured.');
        }

        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->dataBaseUrl.'/stocks/'.strtoupper($symbol).'/bars', [
                'timeframe' => $timeframe,
                'start' => now()->subDays($days)->toDateString(),
                'end' => now()->toDateString(),
                'limit' => 1000,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Alpaca bars request failed. HTTP '
                .$response->status().': '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Trading
    |--------------------------------------------------------------------------
    */

    public function buy(array $data): array
    {
        return $this->submit('buy', $data);
    }

    public function sell(array $data): array
    {
        return $this->submit('sell', $data);
    }

    /**
     * Submit an order to Alpaca (or the deterministic mock transport).
     *
     * Returns the raw provider order payload; status normalisation lives in
     * AlpacaStockBroker so this class stays a thin transport.
     */
    protected function submit(string $side, array $data): array
    {
        $this->guardLiveTrading();

        $payload = $this->buildPayload($side, $data);

        if ($this->isMockEnabled()) {
            return $this->mockOrder($payload);
        }

        if (! $this->credentialsConfigured()) {
            throw new RuntimeException('Alpaca credentials are not configured.');
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->post($this->baseUrl.'/v2/orders', $payload);
        } catch (ConnectionException $exception) {
            throw new ProviderRequestException(
                'Alpaca order submission outcome is unknown: '.$exception->getMessage(),
                true,
                0,
                $exception
            );
        }

        if ($response->failed()) {
            $status = $response->status();

            throw new ProviderRequestException(
                'Alpaca order submission failed. HTTP '.$status.'.',
                $status === 429 || $status >= 500,
                $status
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Request cancellation of a provider order.
     *
     * The local order is only confirmed cancelled once reconciliation observes
     * the provider state — this never refunds by itself.
     */
    public function cancelOrder(string $providerOrderId): array
    {
        $this->guardLiveTrading();

        if ($this->isMockEnabled()) {
            $order = self::$mockOrders[$providerOrderId] ?? [
                'id' => $providerOrderId,
                'filled_qty' => '0',
                'qty' => '0',
            ];

            $order['status'] = 'canceled';
            $order['canceled_at'] = now()->toIso8601String();
            $order['updated_at'] = now()->toIso8601String();
            self::$mockOrders[$providerOrderId] = $order;

            return $order;
        }

        if (! $this->credentialsConfigured()) {
            throw new RuntimeException('Alpaca credentials are not configured.');
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->delete(
                    $this->baseUrl.'/v2/orders/'
                    .rawurlencode($providerOrderId)
                );
        } catch (ConnectionException $exception) {
            throw new ProviderRequestException(
                'Alpaca cancellation outcome is unknown: '.$exception->getMessage(),
                true,
                0,
                $exception
            );
        }

        if ($response->failed()) {
            $status = $response->status();

            throw new ProviderRequestException(
                'Alpaca order cancellation failed. HTTP '.$status.'.',
                $status === 429 || $status >= 500,
                $status
            );
        }

        return $response->json()
            ?? ['id' => $providerOrderId, 'status' => 'cancel_requested'];
    }

    public function placeOrder($symbol, $qty, $side)
    {
        return $this->placeAdvancedOrder([
            'symbol' => $symbol,
            'qty' => $qty,
            'side' => $side,
            'type' => 'market',
            'time_in_force' => 'gtc',
        ]);
    }

    public function placeAdvancedOrder($data)
    {
        $side = strtolower((string) ($data['side'] ?? ''));

        if (! in_array($side, ['buy', 'sell'], true)) {
            throw new RuntimeException('Alpaca order side must be buy or sell.');
        }

        return $this->submit($side, $data);
    }

    public function getOrders()
    {
        return $this->orders();
    }

    public function getPositions()
    {
        return $this->positions();
    }

    public function getAccount()
    {
        if ($this->isMockEnabled()) {
            return [
                'id' => 'mock-alpaca-account',
                'status' => 'ACTIVE',
                'currency' => 'USD',
            ];
        }

        $this->requireCredentials();

        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->baseUrl.'/v2/account');

        if ($response->failed()) {
            throw new RuntimeException('Alpaca account request failed.');
        }

        return $response->json() ?? [];
    }

    public function positions(): array
    {
        if ($this->isMockEnabled()) {
            return [];
        }

        $this->requireCredentials();

        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->baseUrl.'/v2/positions');

        if ($response->failed()) {
            throw new RuntimeException('Alpaca positions request failed.');
        }

        $positions = $response->json() ?? [];

        return is_array($positions) ? $positions : [];
    }

    public function assets(array $query = []): array
    {
        if ($this->isMockEnabled()) {
            return [];
        }

        $this->requireCredentials();

        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->dataBaseUrl.'/assets', array_merge([
                'status' => 'active',
                'asset_class' => 'us_equity',
            ], $query));

        if ($response->failed()) {
            throw new RuntimeException('Alpaca assets request failed.');
        }

        $assets = $response->json() ?? [];

        return is_array($assets) ? $assets : [];
    }

    /**
     * Orders known to Alpaca, used for reconciliation.
     */
    public function orders(array $query = []): array
    {
        if ($this->isMockEnabled()) {
            return array_values(self::$mockOrders);
        }

        $this->requireCredentials();

        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get(
                $this->baseUrl.'/v2/orders',
                array_merge(['limit' => 500, 'direction' => 'desc'], $query)
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Alpaca orders request failed. HTTP '
                .$response->status().': '
                .$response->body()
            );
        }

        return $response->json() ?? [];
    }

    public function portfolio(int $userId): array
    {
        return $this->positions();
    }

    public function history(int $userId): array
    {
        return $this->orders(['status' => 'closed', 'limit' => 100]);
    }

    protected function headers(): array
    {
        return [
            'APCA-API-KEY-ID' => (string) $this->key,
            'APCA-API-SECRET-KEY' => (string) $this->secret,
        ];
    }

    protected function credentialsConfigured(): bool
    {
        return filled($this->key) && filled($this->secret);
    }

    protected function requireCredentials(): void
    {
        if (! $this->credentialsConfigured()) {
            throw new RuntimeException('Alpaca credentials are not configured.');
        }
    }

    protected function buildPayload(string $side, array $data): array
    {
        $payload = [
            'symbol' => strtoupper((string) $data['symbol']),
            'side' => $side,
            'type' => strtolower($data['type'] ?? 'market'),
            'time_in_force' => strtolower($data['time_in_force'] ?? 'day'),
        ];

        /**
         * Fractional quantities are valid for Alpaca, so qty stays a float and
         * is never cast to int (a cast would turn 0.25 into 0).
         */
        $payload['qty'] = (string) $this->normaliseQuantity(
            $data['qty'] ?? $data['quantity'] ?? 0
        );

        if ($payload['type'] === 'limit') {
            $limit = $data['limit_price'] ?? null;

            if ($limit === null || (float) $limit <= 0) {
                throw new RuntimeException('Limit price is required.');
            }

            $payload['limit_price'] = (string) $limit;
        }

        if ($payload['type'] === 'stop') {
            $stop = $data['stop_price'] ?? null;

            if ($stop === null || (float) $stop <= 0) {
                throw new RuntimeException('Stop price is required.');
            }

            $payload['stop_price'] = (string) $stop;
        }

        if (! empty($data['client_order_id'])) {
            $clientOrderId = (string) $data['client_order_id'];

            if (strlen($clientOrderId) > 48) {
                throw new RuntimeException('Alpaca client_order_id may not exceed 48 characters.');
            }

            $payload['client_order_id'] = $clientOrderId;
        }

        // Bracket legs and order class pass through untouched.
        foreach (['order_class', 'take_profit', 'stop_loss'] as $extra) {
            if (! empty($data[$extra])) {
                $payload[$extra] = $data[$extra];
            }
        }

        return $payload;
    }

    protected function normaliseQuantity(mixed $qty): float
    {
        $value = is_numeric($qty) ? (float) $qty : 0.0;

        // Keep fractions intact but avoid "1.00000000" style strings.
        return $value === floor($value) ? (float) (int) $value : $value;
    }

    /**
     * Safety guard: never place a real order unless live trading has been
     * explicitly activated. Mock mode is allowed because no real order leaves
     * Xavier.
     */
    public function guardLiveTrading(): void
    {
        $enabled = filter_var(
            config('services.alpaca.live_trading_enabled', false),
            FILTER_VALIDATE_BOOL
        );

        if (! $enabled && ! $this->isMockEnabled()) {
            throw new RuntimeException('Alpaca live trading is disabled.');
        }
    }

    public function isMockEnabled(): bool
    {
        return filter_var(
            config('services.alpaca.mock', true),
            FILTER_VALIDATE_BOOL
        );
    }

    public function resetMockState(): void
    {
        self::$mockOrders = [];
    }

    protected function mockOrder(array $payload): array
    {
        $now = now()->toIso8601String();

        /**
         * An accepted mock order is reported accepted/open with a zero filled
         * quantity. It must never be reported filled: execution is a separate
         * provider event discovered through reconciliation.
         */
        $id = (string) Str::uuid();
        $order = [
            'id' => $id,
            'client_order_id' => $payload['client_order_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'submitted_at' => $now,
            'expired_at' => null,
            'canceled_at' => null,
            'symbol' => $payload['symbol'],
            'side' => $payload['side'],
            'type' => $payload['type'],
            'order_type' => $payload['type'],
            'time_in_force' => $payload['time_in_force'],
            'limit_price' => $payload['limit_price'] ?? null,
            'stop_price' => $payload['stop_price'] ?? null,
            'qty' => $payload['qty'],
            'filled_qty' => '0',
            'filled_avg_price' => null,
            'order_class' => '',
            'status' => 'accepted',
            'extended_hours' => false,
            'asset_class' => 'us_equity',
        ];

        self::$mockOrders[$id] = $order;

        return $order;
    }
}
