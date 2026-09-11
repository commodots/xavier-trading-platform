<?php

namespace App\Console\Commands;

use App\Services\CSL\CslStockClient;
use App\Services\CSL\CslTradeXClient;
use Illuminate\Console\Command;
use Throwable;

class TestCslApi extends Command
{
    protected $signature = 'csl:api-test';

    protected $description =
        'Test CSL ST and XT API connectivity';

    public function handle(
        CslStockClient $st,
        CslTradeXClient $xt
    ): int {

        $this->info('Testing CSL ST API...');

        try {

            $markets = $st->markets();

            $this->info(
                'ST Markets request succeeded.'
            );

            $this->line(
                json_encode(
                    $markets,
                    JSON_PRETTY_PRINT
                )
            );

        } catch (Throwable $e) {

            $this->error(
                'ST failed: '.$e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info('Testing CSL XT API...');

        try {

            $status = $xt->marketStatus();

            $this->info(
                'XT Market Status request succeeded.'
            );

            $this->line(
                json_encode(
                    $status,
                    JSON_PRETTY_PRINT
                )
            );

        } catch (Throwable $e) {

            $this->error(
                'XT failed: '.$e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(
            'CSL ST and XT API tests completed.'
        );

        return self::SUCCESS;
    }
}
