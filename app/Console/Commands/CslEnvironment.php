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

        $this->table(
            ['Setting', 'Value'],
            [
                ['CSL Mode', $mode],
                ['CSL Mock', $mock ? 'YES' : 'NO'],
                ['Live Trading Enabled', $liveTrading ? 'YES' : 'NO'],
                ['ST Base URL', config('services.csl.st_base_url')],
                ['XT Base URL', config('services.csl.xt_base_url')],
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
