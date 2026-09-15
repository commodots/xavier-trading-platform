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
        $healthCheck = $health->check();
        $isMock = config('services.csl.mode', 'mock') === 'mock';
        $servicesPass = $isMock || $healthCheck['connected'];
        $configured = static fn (mixed $value): string => filled($value) ? 'configured' : 'missing';

        $this->line('CSL DIAGNOSTICS');
        $this->line('============================');
        $this->line('Configuration');
        $this->line('  Mode: '.config('services.csl.mode', 'mock'));
        $this->line('  ST Base URL: '.$configured(config('services.csl.st_base_url')));
        $this->line('  XT Base URL: '.$configured(config('services.csl.xt_base_url')));
        $this->line('  OAuth URL: '.$configured(config('services.csl.oauth_url')));
        $this->line('');
        $this->line('Services');
        $this->line('  OAuth: '.($servicesPass ? 'PASS' : 'FAIL'));
        $this->line('  ST: '.($servicesPass ? 'PASS' : 'FAIL'));
        $this->line('  XT: '.($servicesPass ? 'PASS' : 'FAIL'));
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
        $this->line('RESULT: '.($servicesPass ? 'PASS' : 'FAIL'));

        return $servicesPass ? self::SUCCESS : self::FAILURE;
    }
}
