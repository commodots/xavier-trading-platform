<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class TestDojahConnection extends Command
{
    // The terminal command string you will type
    protected $signature = 'dojah:test';

    protected $description = 'Quick check to verify Dojah API connectivity';

    public function handle()
    {
        $this->info('Connecting to Dojah...');

        
        $dojah = new \Dojah\Client(
            Authorization: config('services.dojah.secret_key'),
            AppId: config('services.dojah.app_id'),
            host: 'https://sandbox.dojah.io'
        );

        $profile_id = "WC7117469"; 

        try {
            $result = $dojah->aML->getScreeningInfo(profile_id: $profile_id);
            
            $this->info('Success!');
            dump($result);
            
        } catch (Exception $e) {
            $this->error('Exception when calling AMLApi->getScreeningInfo: ' . $e->getMessage());
        }
    }
}