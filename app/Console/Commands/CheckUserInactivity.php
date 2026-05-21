<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class CheckUserInactivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:check-inactivity {days=30 : The number of trailing days to verify lack of session activity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $signature_description = 'Audit account session interaction markers and isolate stale system flags.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->argument('days');
        $thresholdDate = now()->subDays($days);

        $this->info("Scanning profiles inactive since: {$thresholdDate->toDateTimeString()} ({$days} days)...");

        // Locate profiles whose last login activity or updated state is older than the threshold, ignoring already flagged records
        $inactiveUsers = User::where(function ($query) use ($thresholdDate) {
                $query->where('last_active_at', '<', $thresholdDate)
                      ->orWhereNull('last_active_at');
            })
            ->whereNotIn('subscription_status', ['inactive', 'suspended'])
            ->where('role', '!=', 'admin')
            ->get();

        if ($inactiveUsers->isEmpty()) {
            $this->info('No active accounts match the requested inactivity threshold bounds.');
            return Command::SUCCESS;
        }

        $this->info("Flagging " . $inactiveUsers->count() . " inactive user accounts...");

        foreach ($inactiveUsers as $user) {
            try {
                DB::transaction(function () use ($user) {
                    $lastActive = $user->last_active_at ?? $user->created_at;
                    $diff = (int) $lastActive->diffInDays(now());

                    if ($diff >= 60) {
                        $user->update(['subscription_status' => 'inactive']);
                        $user->notify(new \App\Notifications\InactivityWarningNotification($diff));
                    } elseif ($diff >= 30) {
                        $user->notify(new \App\Notifications\InactivityWarningNotification($diff));
                    }
                });

            } catch (\Throwable $e) {
                Log::error("Failed to update inactivity records for User ID {$user->id}: " . $e->getMessage());
            }
        }

        $this->info('Inactivity sweep execution sequence finished.');
        return Command::SUCCESS;
    }
}