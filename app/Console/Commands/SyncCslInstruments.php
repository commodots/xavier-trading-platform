<?php

namespace App\Console\Commands;

use App\Services\CSL\CslInstrumentService;
use Illuminate\Console\Command;
use Throwable;

class SyncCslInstruments extends Command
{
    protected $signature = 'csl:sync-instruments';

    protected $description =
        'Synchronize CSL instruments into Xavier symbols';

    public function handle(
        CslInstrumentService $service
    ): int {

        try {

            $count = $service->sync();

            $this->info(
                "CSL instruments synchronized: {$count}"
            );

            return self::SUCCESS;

        } catch (Throwable $e) {

            $this->error(
                'CSL instrument sync failed: '
                .$e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
