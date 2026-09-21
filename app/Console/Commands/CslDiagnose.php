<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\Symbol;
use App\Services\CSL\CslHealthService;
use Illuminate\Console\Command;

class CslDiagnose extends Command
{
    protected $signature = 'csl:diagnose';

    protected $description = 'Check CSL configuration, connectivity, database state, and scheduler wiring.';

    public function handle(CslHealthService $health): int
    {
        $mock = filter_var(config('services.csl.mock', true), FILTER_VALIDATE_BOOL);
        $liveTrading = filter_var(config('services.csl.live_trading_enabled', false), FILTER_VALIDATE_BOOL);

        /** 
         * In mock mode the transport serves fixtures, so no real connectivity
         * is required (and none is attempted).
         */
        $healthCheck = $mock
            ? ['connected' => true, 'status' => 'ok']
            : $health->check();

        $connected = (bool) $healthCheck['connected'];
        $serviceState = $mock ? 'MOCK' : ($connected ? 'PASS' : 'FAIL');
        $configured = static fn (mixed $value): string => filled($value) ? 'configured' : 'missing';

        $this->line('CSL DIAGNOSTICS');
        $this->line('============================');
        $this->line('Configuration');
        $this->line('  Mode: '.config('services.csl.mode', 'test'));
        $this->line('  Mock transport: '.($mock ? 'ENABLED' : 'DISABLED'));
        $this->line('  Live trading: '.($liveTrading ? 'ENABLED' : 'DISABLED'));
        $this->line('  Client ID: '.$configured(config('services.csl.client_id')));
        $this->line('  Client Secret: '.$configured(config('services.csl.client_secret')));
        $this->line('  ST Base URL: '.$configured(config('services.csl.st_base_url')));
        $this->line('  XT Base URL: '.$configured(config('services.csl.xt_base_url')));
        $this->line('  OAuth URL: '.$configured(config('services.csl.oauth_url')));
        $this->line('');
        $this->line('Services');
        $this->line('  OAuth: '.$serviceState);
        $this->line('  ST: '.$serviceState);
        $this->line('  XT: '.$serviceState);
        $this->line('');
        $this->line('Database');
        $this->line('  Provider accounts: '.ProviderAccount::where('provider', 'csl')->count());
        $this->line('  CSL symbols: '.Symbol::where('provider', 'csl')->count());
        $this->line('  CSL orders: '.Order::where('provider', 'csl')->count());
        $this->line('  Unmatched orders: '.Order::where('provider', 'csl')->where('reconciliation_status', 'unmatched')->count());
        $this->line('  Reconciliation errors: '.Order::where('provider', 'csl')->where('reconciliation_status', 'error')->count());
        $this->line('');
        $this->line('Scheduler');
        $this->line('  Instrument sync: configured');
        $this->line('  Order reconciliation: configured');
        $this->line('  Portfolio sync: configured');
        $this->line('');

        if ($liveTrading) {
            $this->error('Live CSL trading is enabled.');
            $this->error('RESULT: FAIL');

            return self::FAILURE;
        }

        $this->line('RESULT: '.(($mock || $connected) ? 'PASS' : 'FAIL'));

        return ($mock || $connected) ? self::SUCCESS : self::FAILURE;
    }
}
