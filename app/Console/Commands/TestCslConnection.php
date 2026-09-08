<?php

namespace App\Console\Commands;

use App\Services\CSL\CslClient;
use Illuminate\Console\Command;
use Throwable;

class TestCslConnection extends Command
{
    protected $signature = 'csl:test';

    protected $description = 'Test the Xavier connection to CSL';

    public function handle(CslClient $csl): int
    {
        $this->info('Testing CSL authentication...');

        try {
            $token = $csl->getAccessToken();

            if (!$token) {
                $this->error('CSL did not return an access token.');

                return self::FAILURE;
            }

            $this->info('CSL authentication successful.');

            $this->line(
                'Access token received: '
                . substr($token, 0, 10)
                . '********'
            );

            return self::SUCCESS;

        } catch (Throwable $e) {

            $this->error(
                'CSL connection failed: ' . $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}