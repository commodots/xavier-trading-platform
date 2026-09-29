<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\Symbol;
use App\Services\CSL\CslHealthService;
use App\Services\CSL\CslInstrumentService;
use App\Services\CSL\CslMarketDataProvider;
use App\Services\CSL\CslStockClient;
use App\Services\CSL\CslTradeXClient;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Single operational health check for the CSL (NGX) integration.
 *
 * The command deliberately answers two complementary questions in one
 * place, so an administrator never has to guess which command to run:
 *
 *  1. Is the integration configured and wired correctly?
 *     (configuration, database state, scheduler wiring)
 *  2. Can Xavier actually talk to CSL and submit NGX orders right now?
 *     (credentials, OAuth, ST/XT APIs, account mapping, instruments and
 *     market status)
 *
 * A green configuration, database and scheduler report next to a failing
 * credential check is exactly the case this command exists to surface: the
 * integration used to look healthy while trading was impossible.
 *
 * Secrets and access tokens are never printed, a closed NGX market is
 * reported as a status rather than a failure, and a non-zero exit code is
 * returned when a critical readiness check fails.
 */
class CslDiagnose extends Command
{
    protected $signature = 'csl:diagnose';

    protected $description = 'Diagnose CSL configuration, authentication, API, account, instrument, market, database and scheduler readiness.';

    /**
     * Whether the deterministic mock transport is serving CSL payloads.
     */
    protected bool $mock = false;

    /**
     * Whether CSL can be reached at all (mock, or authenticated ST/XT).
     * Guards against a second doomed call on an already dead link.
     */
    protected bool $reachable = false;

    /**
     * Raw CSL health payload, reused so each API is probed only once.
     */
    protected array $health = [];

    public function handle(
        CslHealthService $health,
        CslTradeXClient $xt,
        CslStockClient $st,
        CslInstrumentService $instruments,
        CslMarketDataProvider $marketData,
        Schedule $schedule
    ): int {
        $this->mock = filter_var(
            config('services.csl.mock', true),
            FILTER_VALIDATE_BOOL
        );

        /**
         * CslHealthService already performs the real credential, OAuth, ST
         * and XT probes (and short-circuits them under the mock
         * transport), so reuse it as the single source of truth for
         * connectivity rather than repeating every call.
         */
        $this->health = $health->check();

        $this->reachable = $this->mock
            || (bool) ($this->health['connected'] ?? false);

        $sections = [
            'Configuration' => $this->checkConfiguration(),

            'Authentication / API' => $this->checkApi(),

            'Provider Account' => $this->checkAccount(),

            'Instruments' => $this->checkInstruments($st, $instruments),

            'Market' => $this->checkMarket($xt, $marketData),

            'Database' => $this->checkDatabase(),

            'Scheduler' => $this->checkScheduler($schedule),
        ];

        $failed = false;

        $this->line('');
        $this->line('CSL DIAGNOSTIC');
        $this->line('============================================================');

        foreach ($sections as $title => $checks) {
            $this->line('');
            $this->line($title);
            $this->line(str_repeat('-', strlen($title)));

            foreach ($checks as $label => $result) {
                $this->render($label, $result);

                $failed = $failed || (bool) $result['failed'];
            }
        }

        $this->line('');
        $this->line('Overall');
        $this->line('-------');

        if ($failed) {
            $this->line(sprintf(
                '  %-32s <fg=red>%s</>',
                'CSL integration status',
                'NOT READY'
            ));

            return self::FAILURE;
        }

        $this->line(sprintf(
            '  %-32s <fg=green>%s</>',
            'CSL integration status',
            'READY'
        ));

        return self::SUCCESS;
    }

    /**
     * Configuration: mode, transports and endpoints.
     *
     * Credentials are checked here (never printed) because every other
     * readiness check depends on them.
     */
    protected function checkConfiguration(): array
    {
        $liveTrading = filter_var(
            config('services.csl.live_trading_enabled', false),
            FILTER_VALIDATE_BOOL
        );

        $credentials = filled(config('services.csl.client_id'))
            && filled(config('services.csl.client_secret'));

        return [
            'Mode' => $this->result(
                (string) config('services.csl.mode', 'test')
            ),

            'Mock transport' => $this->result(
                $this->mock ? 'ENABLED' : 'DISABLED',
                note: $this->mock
                    ? 'fixtures served locally'
                    : 'live CSL endpoints'
            ),

            /**
             * Preserved guard: live trading enabled during a diagnostic run
             * is still treated as a failure so it cannot pass unnoticed.
             */
            'Live trading' => $this->result(
                $liveTrading ? 'ENABLED' : 'DISABLED',
                failed: $liveTrading,
                note: $liveTrading
                    ? 'live order submission is on'
                    : null
            ),

            'CSL credentials configured' => $this->result(
                $credentials ? 'OK' : 'FAIL',
                failed: ! $credentials
            ),

            'ST base URL' => $this->url(
                config('services.csl.st_base_url')
            ),

            'XT base URL' => $this->url(
                config('services.csl.xt_base_url')
            ),

            'OAuth URL' => $this->url(
                config('services.csl.oauth_url')
            ),
        ];
    }

    /**
     * Authentication and API availability.
     *
     * Each line answers a different question: can we obtain a token, can
     * we reach the security-trading API, and can we reach the execution
     * API. They are reported separately so an operator knows exactly which
     * leg is broken.
     */
    protected function checkApi(): array
    {
        $checks = $this->health['checks'] ?? [];

        $oauth = (bool) ($checks['oauth'] ?? false);
        $st = (bool) ($checks['st'] ?? false);
        $xt = (bool) ($checks['xt'] ?? false);

        $connected = (bool) ($this->health['connected'] ?? false);

        /**
         * Under the mock transport nothing is actually called, so report
         * MOCK rather than implying a live CSL round-trip happened.
         */
        $state = fn (bool $ok): string => $this->mock
            ? 'MOCK'
            : ($ok ? 'OK' : 'FAIL');

        return [
            'CSL connectivity' => $this->result(
                $this->mock ? 'MOCK' : ($connected ? 'OK' : 'FAIL'),
                failed: ! $this->mock && ! $connected
            ),

            'OAuth authentication' => $this->result(
                $state($oauth),
                failed: ! $this->mock && ! $oauth,
                note: $oauth ? 'access token obtained' : 'no access token'
            ),

            'ST API connectivity' => $this->result(
                $state($st),
                failed: ! $this->mock && ! $st,
                note: $oauth ? null : 'requires a valid token'
            ),

            'XT API connectivity' => $this->result(
                $state($xt),
                failed: ! $this->mock && ! $xt,
                note: $oauth ? null : 'requires a valid token'
            ),
        ];
    }

    /**
     * Provider account mapping: user -> provider account -> market account.
     *
     * Reaching the API is not enough: without an active, mapped market
     * account an NGX order cannot be routed at all.
     */
    protected function checkAccount(): array
    {
        try {
            $accounts = ProviderAccount::query()
                ->where('provider', 'csl')
                ->get();

            $mapped = $accounts->filter(
                fn (ProviderAccount $account): bool => filled(
                    $account->market_account_id
                )
            );

            $active = $mapped->filter(
                fn (ProviderAccount $account): bool => $account->status === 'active'
            );

            $statuses = $accounts
                ->map(
                    fn (ProviderAccount $account): string => (string) $account->status
                )
                ->unique()
                ->filter()
                ->implode(', ');

            return [
                'CSL account mapping' => $this->result(
                    $accounts->isNotEmpty() ? 'OK' : 'FAIL',
                    failed: $accounts->isEmpty(),
                    note: $accounts->count().' CSL provider account(s)'
                ),

                'Market account mapped' => $this->result(
                    $mapped->isNotEmpty() ? 'OK' : 'FAIL',
                    failed: $mapped->isEmpty(),
                    note: $mapped->count().' with a market account id'
                ),

                'Trading status' => $this->result(
                    $active->isNotEmpty() ? 'ACTIVE' : ($statuses ?: 'NONE'),
                    failed: $active->isEmpty(),
                    note: $active->isNotEmpty()
                        ? $active->count().' tradable account(s)'
                        : 'no active CSL account'
                ),
            ];
        } catch (Throwable $e) {
            return [
                'CSL account mapping' => $this->result(
                    'UNAVAILABLE',
                    failed: true,
                    note: $this->safeMessage($e)
                ),

                'Market account mapped' => $this->result('UNKNOWN'),

                'Trading status' => $this->result('UNKNOWN'),
            ];
        }
    }

    /**
     * Instruments: can CSL list them, and has Xavier mapped them?
     *
     * These are separate questions: an authenticated but unsynced
     * installation can authenticate to CSL yet still be unable to price
     * or submit an NGX order.
     */
    protected function checkInstruments(
        CslStockClient $st,
        CslInstrumentService $instruments
    ): array {
        $count = null;

        if ($this->reachable) {
            try {
                $count = count(
                    $instruments->rows($st->instruments())
                );
            } catch (Throwable) {
                $count = null;
            }
        }

        $available = $count !== null && $count > 0;

        $mapped = 0;
        $withMarket = 0;

        try {
            $mapped = Symbol::query()
                ->where('provider', 'csl')
                ->count();

            $withMarket = Symbol::query()
                ->where('provider', 'csl')
                ->whereNotNull('market_id')
                ->count();
        } catch (Throwable) {
            $mapped = -1;
            $withMarket = 0;
        }

        return [
            'CSL instrument list available' => $this->result(
                $this->reachable ? ($available ? 'OK' : 'FAIL') : 'UNKNOWN',
                failed: $this->reachable && ! $available,
                note: $count === null
                    ? 'CSL API not reachable'
                    : $count.' instrument(s) from CSL'
            ),

            'Local CSL symbols mapped' => $this->result(
                $mapped > 0 ? 'OK' : 'FAIL',
                failed: $mapped <= 0,
                note: $mapped > 0
                    ? $mapped.' local symbol(s)'
                    : 'run csl:sync-instruments'
            ),

            'Symbols with market mapped' => $this->result(
                $withMarket > 0 ? 'OK' : 'WARN',
                note: $withMarket.' of '.max($mapped, 0).' symbol(s) carry a market id'
            ),
        ];
    }

    /**
     * Current NGX market status.
     *
     * A closed market is a perfectly healthy integration, so it is
     * reported as a status and never fails the diagnostic.
     */
    protected function checkMarket(
        CslTradeXClient $xt,
        CslMarketDataProvider $marketData
    ): array {
        if (! $this->reachable) {
            return [
                'NGX market status' => $this->result(
                    'UNKNOWN',
                    note: 'CSL API not reachable'
                ),
            ];
        }

        try {
            $payload = $this->health['market_status'] ?? [];

            /**
             * The health probe already called XT outside mock mode, so
             * only the mock transport needs the fixture read here.
             */
            if ($payload === []) {
                $payload = $xt->marketStatus();
            }

            return [
                'NGX market status' => $this->result(
                    $this->marketState(
                        $marketData->extract($payload, 'GetCRXTMarketStatus')
                    ),
                    note: 'informational only'
                ),
            ];
        } catch (Throwable $e) {
            return [
                'NGX market status' => $this->result(
                    'UNKNOWN',
                    note: $this->safeMessage($e)
                ),
            ];
        }
    }

    /**
     * Database state, including the provider tables this integration needs.
     */
    protected function checkDatabase(): array
    {
        $tables = [
            'provider_accounts',
            'provider_sync_logs',
            'symbols',
            'orders',
            'trades',
            'portfolios',
        ];

        try {
            $missing = array_values(array_filter(
                $tables,
                fn (string $table): bool => ! Schema::hasTable($table)
            ));

            return [
                'CSL tables' => $this->result(
                    $missing === [] ? 'OK' : 'FAIL',
                    failed: $missing !== [],
                    note: $missing === []
                        ? count($tables).' provider tables present'
                        : 'missing: '.implode(', ', $missing)
                ),

                'Provider accounts' => $this->count(
                    fn (): int => ProviderAccount::where('provider', 'csl')->count()
                ),

                'Provider symbols' => $this->count(
                    fn (): int => Symbol::where('provider', 'csl')->count()
                ),

                'CSL orders' => $this->count(
                    fn (): int => Order::where('provider', 'csl')->count()
                ),

                'Unmatched orders' => $this->count(
                    fn (): int => Order::where('provider', 'csl')
                        ->where('reconciliation_status', 'unmatched')
                        ->count()
                ),

                'Reconciliation errors' => $this->count(
                    fn (): int => Order::where('provider', 'csl')
                        ->where('reconciliation_status', 'error')
                        ->count()
                ),
            ];
        } catch (Throwable $e) {
            return [
                'CSL tables' => $this->result(
                    'UNAVAILABLE',
                    failed: true,
                    note: $this->safeMessage($e)
                ),
            ];
        }
    }

    /**
     * Scheduler wiring.
     *
     * The commands are not executed here: the diagnostic only verifies
     * that the schedule still drives the CSL background work, because
     * missing wiring means orders and portfolios would silently stop
     * syncing.
     */
    protected function checkScheduler(Schedule $schedule): array
    {
        $expected = [
            'Instrument sync' => 'csl:sync-instruments',
            'Order reconciliation' => 'csl:reconcile-orders',
            'Portfolio sync' => 'csl:sync-portfolios',
            'Account sync' => 'csl:sync-account',
        ];

        try {
            $events = $schedule->events();

            /**
             * The schedule is empty only when console routes were not
             * loaded for this process, so report that as unknown rather
             * than declaring a false failure.
             */
            if ($events->isEmpty()) {
                $checks = [];

                foreach ($expected as $label => $command) {
                    $checks[$label] = $this->result(
                        'UNKNOWN',
                        note: $command.' not inspectable'
                    );
                }

                return $checks;
            }

            $registered = strtolower($events
                ->map(fn ($event): string => $this->describeEvent($event))
                ->implode(' | '));

            $checks = [];

            foreach ($expected as $label => $command) {
                $wired = str_contains($registered, $command);

                $checks[$label] = $this->result(
                    $wired ? 'OK' : 'FAIL',
                    failed: ! $wired,
                    note: $wired ? $command : $command.' is not scheduled'
                );
            }

            return $checks;
        } catch (Throwable $e) {
            $checks = [];

            foreach ($expected as $label => $command) {
                $checks[$label] = $this->result(
                    'UNAVAILABLE',
                    failed: true,
                    note: $this->safeMessage($e)
                );
            }

            return $checks;
        }
    }

    /**
     * Flatten a scheduled event into searchable text.
     */
    protected function describeEvent(mixed $event): string
    {
        if (! $event instanceof ScheduledEvent) {
            return '';
        }

        return (string) $event->command
            .' '.(string) $event->description
            .' '.(string) $event->expression;
    }

    /**
     * Build one diagnostic result row.
     */
    protected function result(
        string $status,
        bool $failed = false,
        ?string $note = null
    ): array {
        return [
            'status' => $status,
            'failed' => $failed,
            'note' => $note,
        ];
    }

    /**
     * Print one result row, aligned and colour-coded.
     */
    protected function render(string $label, array $result): void
    {
        $status = (string) $result['status'];

        $colour = match (true) {
            (bool) $result['failed'] => 'red',
            in_array($status, [
                'OK', 'READY', 'ACTIVE', 'MOCK', 'ENABLED', 'DISABLED',
            ], true) => 'green',
            in_array($status, ['WARN', 'UNKNOWN', 'NONE'], true) => 'yellow',
            default => 'default',
        };

        $line = sprintf(
            '  %-32s <fg=%s>%s</>',
            $label,
            $colour,
            $status
        );

        if (filled($result['note'] ?? null)) {
            $line .= '  ('.$result['note'].')';
        }

        $this->line($line);
    }

    /**
     * Report a configured endpoint without disclosing anything sensitive.
     */
    protected function url(mixed $value): array
    {
        return $this->result(
            filled($value) ? 'OK' : 'FAIL',
            failed: ! filled($value),
            note: filled($value) ? 'configured' : 'missing'
        );
    }

    /**
     * Report a row count, keeping the historical database detail.
     */
    protected function count(callable $query): array
    {
        try {
            return $this->result('OK', note: (string) $query());
        } catch (Throwable $e) {
            return $this->result(
                'UNAVAILABLE',
                failed: true,
                note: $this->safeMessage($e)
            );
        }
    }

    /**
     * Derive an OPEN/CLOSED state from the CSL market status rows.
     *
     * Returns UNKNOWN when the payload carries no recognisable state so
     * the diagnostic never guesses.
     */
    protected function marketState(array $rows): string
    {
        foreach ($this->normaliseRows($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $market = strtoupper(trim((string) (
                $row['market_code']
                ?? $row['market_id']
                ?? $row['market']
                ?? ''
            )));

            /**
             * Prefer the NGX row, but accept the first status-bearing row
             * so a differently shaped payload still reports something.
             */
            if ($market !== '' && ! str_contains($market, 'NGX')) {
                continue;
            }

            $state = $this->normaliseState(
                $row['market_status_code']
                ?? $row['market_status']
                ?? $row['trading_status']
                ?? $row['status']
                ?? $row['state']
                ?? null
            );

            if ($state !== null) {
                return $state;
            }
        }

        return 'UNKNOWN';
    }

    /**
     * CSL sometimes returns a single object where a list is expected.
     */
    protected function normaliseRows(array $rows): array
    {
        if ($rows !== [] && ! array_is_list($rows)) {
            return [$rows];
        }

        return $rows;
    }

    /**
     * Map a CSL status value onto OPEN/CLOSED, or null when unclear.
     */
    protected function normaliseState(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'OPEN' : 'CLOSED';
        }

        if (is_numeric($value)) {
            return ((float) $value) > 0 ? 'OPEN' : 'CLOSED';
        }

        return match (strtolower(trim((string) $value))) {
            'open', 'opened', 'active', 'trading', 'live' => 'OPEN',
            'closed', 'close', 'inactive', 'halted', 'suspended' => 'CLOSED',
            default => null,
        };
    }

    /**
     * Exception messages are shown to operators, but any token or secret
     * a provider echoes back is redacted first.
     */
    protected function safeMessage(Throwable $e): string
    {
        $message = (string) preg_replace(
            '/(access_token|client_secret|token)(["\'\s:=]+)[^"\'\s,}]+/i',
            '$1$2***',
            trim($e->getMessage())
        );

        return $message === '' ? 'unexpected error' : $message;
    }
}
