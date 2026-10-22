# Xavier Trading Platform

A full-stack Laravel + Vue 3 trading platform that provides dual-currency (NGN/USD) wallets, cryptocurrency trading, stock/ETF trading, FX conversions, KYC verification, and payment processing integrations.

## Requirements

- **PHP** 8.2+
- **Composer** 2.8+
- **Node.js** 20+ (with npm)
- **Database**: MySQL 8.0+ or SQLite (for testing/development)
- **Redis** (for session/cache/queue in production; optional for local dev)
- **Web server**: Laravel's built-in dev server is sufficient for development

## Quick Start

```bash
# 1. Clone and install dependencies
git clone https://github.com/commodots/xavier-trading-platform.git
cd xavier-trading-platform
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Run database setup
php artisan migrate         
php artisan db:seed          # Seeds demo data, roles, and default admin

# 4. Build frontend assets
npm run build                # or npm run dev for hot-reloading

# 5. Start the dev server
composer dev                 # Runs server, queue, log watcher, and Vite concurrently
```

Then visit `http://localhost` in your browser.

## Environment Configuration

All configuration is driven by environment variables. Copy `.env.example` and adjust as needed:

### Core

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `Xavier` | Application name |
| `APP_ENV` | `local` | `local`, `production`, etc. |
| `APP_KEY` | *(generate)* | Laravel encryption key — run `php artisan key:generate` |
| `APP_DEBUG` | `true` | Enable/disable debug mode |
| `APP_URL` | `http://localhost` | Application base URL |
| `DB_CONNECTION` | `sqlite` | `sqlite` or `mysql` |
| `DB_DATABASE` | `database/database.sqlite` | Database name or path |

### Payment Providers

| Variable | Default | Description |
|---|---|---|
| `PAYSTACK_PUBLIC_KEY` | *(empty)* | Paystack public key |
| `PAYSTACK_SECRET_KEY` | *(empty)* | Paystack secret key |
| `FINCRA_SECRET_KEY` | *(empty)* | Fincra API secret |
| `FINCRA_BASE_URL` | `https://sandboxapi.fincra.com` | Fincra base URL |
| `FINCRA_MODE` | `sandbox` | `sandbox` or `live` |

### KYC (Dojah)

| Variable | Default | Description |
|---|---|---|
| `KYC_PROVIDER` | `dojah` | KYC provider |
| `DOJAH_PUBLIC_KEY` | *(empty)* | Dojah public key |
| `DOJAH_SECRET_KEY` | *(empty)* | Dojah secret key |
| `DOJAH_BASE_URL` | `https://sandbox.dojah.io` | Dojah sandbox URL |

### Stock Broker / Market Data

| Variable | Default | Description |
|---|---|---|
| `STOCK_BROKER` | `mock` | `mock`, `csl`, or `alpaca` |
| `MARKET_DATA_PROVIDER` | `mock` | `mock`, `csl`, or `alpaca` |
| `MARKET_PROVIDER` | `finnhub` | Market data source (`mock` or `finnhub`) |
| `FINNHUB_API_KEY` | *(empty)* | Finnhub API key (for US/global market data) |
| `FINNHUB_SECRET` | *(empty)* | Finnhub incoming webhook secret (for real-time updates) |
| `CSL_MODE` | `test` | CSL environment |
| `CSL_MOCK` | `true` | Enable CSL mock transport (local dev) |
| `CSL_LIVE_TRADING_ENABLED` | `false` | Master switch for live trading |

### Alpaca (US Stocks/ETFs)

| Variable | Default | Description |
|---|---|---|
| `ALPACA_API_KEY` | *(empty)* | Alpaca API key |
| `ALPACA_SECRET_KEY` | *(empty)* | Alpaca secret key |
| `ALPACA_BASE_URL` | `https://paper-api.alpaca.markets` | Trading base URL |
| `ALPACA_DATA_BASE_URL` | `https://data.alpaca.markets/v2` | Market data base URL |
| `ALPACA_MOCK` | `true` | Use Alpaca paper trading in local dev |
| `ALPACA_LIVE_TRADING_ENABLED` | `false` | Master switch for live trading |

### Crypto

| Variable | Default | Description |
|---|---|---|
| `TATUM_API_KEY` | *(empty)* | Tatum API key |
| `TATUM_BASE_URL` | `https://api.tatum.io/v3` | Tatum API base URL |
| `TATUM_WEBHOOK_SECRET` | *(empty)* | Tatum webhook verification secret |

### System Admin (first-time bootstrap)

| Variable | Default | Description |
|---|---|---|
| `SYSTEM_ADMIN_NAME` | `System Administrator` | Seed name for first admin |
| `SYSTEM_ADMIN_EMAIL` | *(empty)* | Seed email for first admin |
| `SYSTEM_ADMIN_PASSWORD` | *(empty)* | Seed password for first admin |

> **Important**: `CSL_LIVE_TRADING_ENABLED` and `ALPACA_LIVE_TRADING_ENABLED` must be `false` in all non-production environments. These act as master kill-switches — real trading and withdrawals are impossible when they are disabled.

## Provider Mock Modes

Xavier uses mock/fake providers by default so the platform can be run locally without real financial credentials or network access. The following mock modes are available:

### 1. Stock Broker — `STOCK_BROKER=mock` (default)

When set to `mock`, the platform uses `MockDriveWealthService` which serves deterministic trading data. No real broker API keys are required. Set to `csl` or `alpaca` to use live integrations.

### 2. Market Data — `MARKET_DATA_PROVIDER=mock` (default)

When set to `mock`, the platform uses `MockPolygonService` for market data feeds. No real market data API key is required. Set to `csl` or `alpaca` to use real feeds.

### 3. CSL (Nigerian Stock Exchange) — `CSL_MOCK=true` (default)

When `true`, the CSL client uses a deterministic fixture transport that answers every API call (OAuth, trades, orders, reconciliations) locally. No CSL credentials are required in this mode. Run `php artisan csl:diagnose` to verify connectivity.

### 4. Alpaca — `ALPACA_MOCK=true` (default)

When `true`, Alpaca operates in paper-trading mode. Combined with `ALPACA_LIVE_TRADING_ENABLED=false`, no real money is at risk.

### 5. Paystack / Fincra — Sandbox

Payment providers default to sandbox/test mode. Paystack requires real API keys for full end-to-end testing but no live transactions will occur when used with test keys. Fincra defaults to `FINCRA_MODE=sandbox`.

### 6. KYC — Dojah Sandbox

Dojah KYC defaults to `https://sandbox.dojah.io`. Mock BVN verification is available for testing.

## Testing

Tests run against an in-memory SQLite database with no real financial transactions possible (live trading is disabled in `phpunit.xml`).

```bash
# Run the full test suite
php artisan test

# Or directly via PHPUnit
php vendor/bin/phpunit

# Run a specific test class
php artisan test --filter=WalletFunctionsTest

# Run a single test method
php artisan test --filter=test_user_can_deposit_funds_and_fees_are_applied

# Run with verbose output
php vendor/bin/phpunit --verbose

# Run only the feature tests
php vendor/bin/phpunit --testsuite=Feature
```

### Test Configuration

- **`phpunit.xml`** defines the testing environment with `CSL_MOCK=true`, `ALPACA_MOCK=true`, and `DB_CONNECTION=sqlite` with `DB_DATABASE=:memory:`.
- Live trading is disabled for all providers in the test environment.
- 3rd party HTTP calls are either mocked or point to sandbox endpoints.

### Running Tests with a Specific Provider Mode

The test environment defaults to mock mode for all providers. To test against a specific provider, adjust the environment in your `.env` or `phpunit.xml`:


## Database

### Running Migrations

```bash
php artisan migrate          # Run all pending migrations
php artisan migrate:fresh    # Drop all tables and re-run (for dev)
php artisan migrate:status   # Check migration status
```

### Seeding Demo Data

```bash
php artisan db:seed          # Run all seeders (demo users, portfolios, wallets, etc.)
php artisan db:seed --class=DatabaseSeeder
```

Demo credentials (after seeding):
- **Admin**: typically seeded via `SYSTEM_ADMIN_*` env vars
- **Demo users**: see `database/seeders/` for seeded user credentials

### Database Configuration

By default, the application uses SQLite for local development. Configure for MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xavier
DB_USERNAME=root
DB_PASSWORD=your-password
```

## Development

### Available Scripts

| Script | Description |
|---|---|
| `composer dev` | Run Laravel dev server, queue listener, log watcher, and Vite dev server concurrently |
| `composer setup` | Full setup: install dependencies, configure .env, generate key, run migrations, build assets |
| `composer test` | Clear config and run test suite |
| `npm run dev` | Start Vite dev server (hot-reloading) |
| `npm run build` | Build frontend assets for production |
| `php artisan csl:diagnose` | Run CSL connectivity diagnostics |
| `php artisan alpaca:reconciliation-report` | Generate Alpaca reconciliation report |

### Architecture

- **Frontend**: Vue 3 + Tailwind CSS + Inertia.js, also includes Vue Router for some flows, Chart.js & ApexCharts for data visualization.
- **Backend**: Laravel 12 API + server-rendered views, Spatie RBAC for permissions, Fortify for auth.
- **Real-time**: Laravel Broadcasting with Pusher for live updates (order books, prices, notifications).
- **Background jobs**: Redis-backed queue with `php artisan queue:listen`.

### Key Integrations

| Integration | Purpose | Config Variables |
|---|---|---|
| **Paystack** | Payment gateway (deposits, withdrawals) | `PAYSTACK_*` |
| **Fincra** | Payout provider and FX rates | `FINCRA_*` |
| **Dojah** | KYC verification (BVN, identity verification) | `DOJAH_*` |
| **CSL** | Nigerian stockbroker integration | `CSL_*`, `STOCK_BROKER=csl` |
| **Alpaca** | US stock/ETF trading | `ALPACA_*`, `STOCK_BROKER=alpaca` |
| **Tatum** | Cryptocurrency wallet & trading | `TATUM_*` |
| **Finnhub** | Global stock/ETF market data (real-time & historical) | `FINNHUB_API_KEY`, `FINNHUB_SECRET` |
| **Pusher** | Real-time broadcasting | `PUSHER_*`, `BROADCAST_DRIVER` |

### Loading Global Stock Market Data

This project supports live global stock market data via **Finnhub**. The workflow involves two steps:

1. **Start the market-stream worker** — a Node.js process that subscribes to real-time symbol updates published over Redis and pushes them to connected clients via Pusher broadcasting:

   ```bash
   node market-stream.js
   ```

   This worker listens on the `symbol-updates` Redis pub/sub channel. When a symbol is tracked (via `POST /api/trade/track-symbol`), the TradeController publishes a `subscribe` event and the worker begins streaming real-time price ticks for that symbol.

2. **Sync global symbols from Finnhub** — populate the local `symbols` table with US and international stock/ETF symbols:

   ```bash
   php artisan stocks:sync
   ```

   This command fetches the complete symbol list from `https://finnhub.io/api/v1/stock/symbol` (for the `US` exchange) and stores them locally. Run this once during initial setup; re-run periodically if you need to refresh the symbol catalog.

   > **Note**: Set `FINNHUB_API_KEY` in your `.env` before running `stocks:sync`. The `FINNHUB_SECRET` is used to verify incoming webhook requests from Finnhub's real-time price feed (handled in `TradeController::updateMarket`).

### Running with Laravel Sail (Docker)

If you have Docker installed, you can use Laravel Sail:

```bash
# Start the Laravel development environment
./vendor/bin/sail up

# Run tests
./vendor/bin/sail test

# Run Artisan commands
./vendor/bin/sail artisan migrate
```

> Note: Ensure `.env` has the correct `DB_CONNECTION` and database settings for the Sail container.

## Debugging

- **Laravel Telescope** (`TELESCOPE_ENABLED=true`, `TELESCOPE_PATH=telescope`): Enabled in local environment. Access at `http://localhost/telescope` when `TELESCOPE_ENABLED=true`. The `TELESCOPE_PATH` env var controls the URL path (defaults to `telescope`). Telescope watchers can be individually enabled/disabled in `config/telescope.php`. Note: Telescope is registered only in the `local` environment (see `AppServiceProvider::register()`).
- **Laravel Pulse** (`PULSE_ENABLED=true`, `PULSE_PATH=pulse`): Real-time application metrics (requests, jobs, exceptions, cache hits). Access at `http://localhost/pulse` when `PULSE_ENABLED=true`. The `PULSE_PATH` env var controls the URL path (defaults to `pulse`). Additional config: `PULSE_STORAGE_DRIVER=database` sets the storage backend; `PULSE_INGEST_DRIVER=storage` controls the ingest driver.
- **Log files**: `storage/logs/laravel.log` or use `php artisan pail` for real-time log viewing.

## Documentation

Additional project documentation is available in the `docs/` directory:
- `docs/Steps-1-environment-report.md` — Environment audit report
- `docs/Steps-1-3-defect-register.md` — Defect register and resolutions
- `docs/audits/*.md` — Detailed audit reports for security, settlements, KYC, sessions, and more


## Contributing

1. Create a feature branch: `git checkout -b feature/my-feature`
2. Make your changes and add tests
3. Run the test suite: `php artisan test`
4. Commit and push: `git push origin feature/my-feature`
5. Open a pull request

See `.github/copilot-instructions.md` for coding conventions and guidelines.

## Security

If you discover a security vulnerability, please report it directly to the development team.
