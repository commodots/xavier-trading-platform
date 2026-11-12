<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CslEnvironment extends Command
{
    protected $signature = 'csl:environment';

    protected $description = 'Display the current CSL integration environment and safety status';

    public function handle(): int
    {
        $mode = config('services.csl.mode');
        $mock = config('services.csl.mock');
        $liveTrading = config('services.csl.live_trading_enabled');
        $baseUrl = rtrim((string) config('services.csl.base_url'), '/');

        $this->table(
            ['Setting', 'Value'],
            [
                ['CSL Mode', $mode],
                ['CSL Mock', $mock ? 'YES' : 'NO'],
                ['Live Trading Enabled', $liveTrading ? 'YES' : 'NO'],
                ['CSL XML Base URL', $baseUrl ?: 'NOT CONFIGURED'],
                ['CRCS URL', $baseUrl ? $baseUrl.'/scs' : 'NOT CONFIGURED'],
                ['CRST URL', $baseUrl ? $baseUrl.'/sst' : 'NOT CONFIGURED'],
                ['CRXT URL', $baseUrl ? $baseUrl.'/sxt' : 'NOT CONFIGURED'],
                ['OAuth URL', config('services.csl.oauth_url')],
                [
                    'Client ID Configured',
                    filled(config('services.csl.client_id')) ? 'YES' : 'NO',
                ],
                [
                    'Client Secret Configured',
                    filled(config('services.csl.client_secret')) ? 'YES' : 'NO',
                ],
            ]
        );

        if ($liveTrading) {
            $this->error(
                'WARNING: CSL live trading is ENABLED.'
            );

            return self::FAILURE;
        }

        $this->info(
            'CSL live trading is safely DISABLED.'
        );

        return self::SUCCESS;
    }
}
