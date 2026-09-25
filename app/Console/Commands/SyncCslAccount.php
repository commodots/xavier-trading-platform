<?php

namespace App\Console\Commands;

use App\Models\ProviderAccount;
use App\Services\CSL\CslAccountService;
use Illuminate\Console\Command;
use Throwable;

class SyncCslAccount extends Command
{
    protected $signature = 'csl:sync-account
        {userId? : Xavier user ID for a single mapping}
        {customerId? : CSL customer ID for a single mapping}';

    protected $description = 'Synchronize one or all mapped user accounts from CSL';

    public function handle(CslAccountService $service): int
    {
        $userId = $this->argument('userId');
        $customerId = $this->argument('customerId');

        if ($userId !== null || $customerId !== null) {
            if ($userId === null || $customerId === null) {
                $this->error('Both userId and customerId are required for a single sync.');

                return self::FAILURE;
            }

            try {
                $count = count($service->syncForUser(
                    (int) $userId,
                    (string) $customerId
                ));
                $this->info("CSL accounts synchronized for user {$userId}: {$count}.");

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $this->error('CSL account sync failed: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        $mappings = ProviderAccount::query()
            ->where('provider', 'csl')
            ->whereNotNull('customer_id')
            ->select(['user_id', 'customer_id'])
            ->distinct()
            ->get();
        $failed = false;

        foreach ($mappings as $mapping) {
            try {
                $service->syncForUser(
                    (int) $mapping->user_id,
                    (string) $mapping->customer_id
                );
            } catch (Throwable $exception) {
                $failed = true;
                $this->error(
                    "User {$mapping->user_id}: {$exception->getMessage()}"
                );
            }
        }

        $this->info('CSL account mappings checked: '.$mappings->count());

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
