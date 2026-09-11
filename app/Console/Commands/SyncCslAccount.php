<?php

namespace App\Console\Commands;

use App\Services\CSL\CslAccountService;
use Illuminate\Console\Command;
use Throwable;

class SyncCslAccount extends Command
{
    protected $signature = 'csl:sync-account
        {userId : Xavier user ID}
        {customerId : Customer ID returned by CSL}';

    protected $description = 'Synchronize one user account mapping from CSL';

    public function handle(CslAccountService $service): int
    {
        try {
            $accounts = $service->syncForUser(
                (int) $this->argument('userId'),
                (string) $this->argument('customerId')
            );

            $this->info('CSL accounts synchronized: '.count($accounts));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('CSL account sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
