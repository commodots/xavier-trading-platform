<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Live Operations Tracking
Schedule::command('settlement:process')->dailyAt('08:00');
Schedule::command('billing:process-quarterly')->quarterly();
Schedule::command('user:check-inactivity 30')->dailyAt('02:00');

// Cleaned up Core Platform Fee Automation (Decoupled from quarterly advisory service)
Schedule::call(function () {
    $billingService = new \App\Services\BillingService();

    \App\Models\User::where('next_fee_due_at', '<=', now())
        ->whereIn('subscription_status', ['trial', 'active'])
        ->chunk(100, function ($users) use ($billingService) {
            foreach ($users as $user) {
                $billingService->chargePlatformFee($user);
            }
        });
})->dailyAt('00:00');