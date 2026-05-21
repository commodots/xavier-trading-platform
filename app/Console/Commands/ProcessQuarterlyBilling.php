<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\Wallet;
use App\Models\NewTransaction;

class ProcessQuarterlyBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:process-quarterly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $signature_description = 'Deduct processing fees and renew quarterly premium advisory subscriptions.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting quarterly advisory billing cycle...');

        // Fetch active subscriptions due or past due for renewal
        $subscriptions = UserSubscription::where('status', 'active')
            ->where('billing_cycle', 'quarterly')
            ->where('next_billing_at', '<=', now())
            ->with('user')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No quarterly subscriptions due for renewal at this time.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($subscriptions->count());
        $bar->start();

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            if (!$user) {
                $subscription->update(['status' => 'cancelled', 'cancellation_reason' => 'Orphaned profile']);
                continue;
            }

            // Assume base cost of $30/quarter for advisory tier systems
            $billingAmount = (float) ($subscription->plan_price ?? 30.00);

            try {
                DB::transaction(function () use ($user, $subscription, $billingAmount) {
                    // Lock wallet to prevent race conditions during bulk execution
                    $wallet = Wallet::where('user_id', $user->id)
                        ->where('currency', 'USD')
                        ->lockForUpdate()
                        ->first();

                    if (!$wallet || $wallet->usd_cleared < $billingAmount) {
                        throw new \Exception("Insufficient cleared USD funds (Available: " . ($wallet->usd_cleared ?? 0) . ")");
                    }

                    // Deduct subscription rate
                    $wallet->decrement('usd_cleared', $billingAmount);
                    
                    // Recalculate absolute fields to maintain state sync
                    $wallet->refresh();
                    $wallet->balance = $wallet->usd_cleared + $wallet->usd_uncleared + $wallet->locked;
                    $wallet->save();

                    NewTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'subscription_fee',
                        'amount' => $billingAmount,
                        'currency' => 'USD',
                        'status' => 'completed',
                        'charge' => 0,
                        'net_amount' => $billingAmount,
                        'meta' => [
                            'description' => "Quarterly renewal fee for advisory status tier: {$subscription->tier_level}",
                            'reference' => 'SUB-QTR-' . strtoupper(uniqid()),
                        ],
                    ]);

                    // Bump billing matrix timeline forward 3 months
                    $subscription->update([
                        'last_billing_at' => now(),
                        'next_billing_at' => now()->addMonths(3),
                        'status' => 'active'
                    ]);
                });

            } catch (\Throwable $e) {
                // If billing fails, downgrade advisory access boundaries safely
                $subscription->update([
                    'status' => 'suspended',
                    'cancellation_reason' => 'Quarterly transaction deduction failed: ' . $e->getMessage()
                ]);

                Log::warning("Quarterly billing failed for User ID {$user->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Quarterly billing run completed.');

        return Command::SUCCESS;
    }
}