<?php

namespace App\Console\Commands;

use App\Models\Ledger;
use App\Models\User;
use App\Models\NewTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessSettlement extends Command
{
    protected $signature = 'settlement:process';

    protected $description = 'Automatically fetch yesterday\'s settlements from Paystack and clear user funds.';

    public function handle()
    {
        $this->info("Fetching latest settlements from Paystack...");

        // Fetch settlements from the last 48 hours
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get('https://api.paystack.co/settlement', [
                'from' => now()->subDays(2)->toIso8601String(),
                'to' => now()->toIso8601String(),
            ]);

        if (!$response->successful()) {
            $this->error("Failed to fetch settlements from Paystack.");
            Log::error("Paystack Settlement API Error", ['response' => $response->json()]);
            return Command::FAILURE;
        }

        $settlements = $response->json()['data'] ?? [];

        if (empty($settlements)) {
            $this->info("No recent settlements found from Paystack.");
            return Command::SUCCESS;
        }

        foreach ($settlements as $settlement) {
            $this->info("Processing Settlement ID: {$settlement['id']} for {$settlement['currency']}");

            // Fetch all transactions inside this specific settlement
            $txResponse = Http::withToken(config('services.paystack.secret_key'))
                ->get("https://api.paystack.co/settlement/{$settlement['id']}/transactions");

            $transactions = $txResponse->json()['data'] ?? [];

            foreach ($transactions as $tx) {
                $reference = $tx['reference'];
                $currency = $tx['currency'] ?? 'NGN';

                // Find the matching Pending Ledger in our DB
                DB::transaction(function () use ($reference, $currency) {
                    $ledger = Ledger::where('reference', $reference)
                        ->where('status', 'pending')
                        ->first();

                    if (!$ledger) return; // Already settled or doesn't exist

                    $user = User::find($ledger->user_id);
                    if (!$user) return;

                    // Settle the Wallet (Moves uncleared -> cleared)
                    $wallet = $user->fxWallet($currency);
                    $wallet->settle();

                    //  Mark Ledger as completed
                    $ledger->update(['status' => 'completed']);

                    NewTransaction::where('reference', $reference)
                        ->update(['status' => 'completed']);

                    $this->info("Auto-Settled {$currency} for Ref: {$reference}");
                });
            }
        }

        $this->info("Automated settlement processing complete.");
        return Command::SUCCESS;
    }
}
