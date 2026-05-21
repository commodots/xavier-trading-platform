<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Live Operations Tracking
Schedule::command('settlements:process')->dailyAt('08:00');
Schedule::command('billing:process-quarterly')->quarterly();
Schedule::command('user:check-inactivity 30')->dailyAt('02:00');

// Trial ending reminders (2 days before expiry)
Schedule::call(function () {
    \App\Models\User::where('subscription_status', 'trial')
        ->whereBetween('trial_ends_at', [now()->addDays(2)->startOfDay(), now()->addDays(2)->endOfDay()])
        ->each(fn ($user) => $user->notify(new \App\Notifications\TrialEndingNotification(2)));
})->dailyAt('09:00');

// Fee due reminders (3 days before next charge)
Schedule::call(function () {
    \App\Models\User::whereIn('subscription_status', ['trial', 'active'])
        ->whereBetween('next_fee_due_at', [now()->addDays(3)->startOfDay(), now()->addDays(3)->endOfDay()])
        ->each(fn ($user) => $user->notify(new \App\Notifications\BillingAlertNotification(1000, 'Platform fee due in 3 days')));
})->dailyAt('09:00');

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