<?php

namespace App\Console\Commands;

use App\Models\Ledger;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSettlement extends Command
{
    /**
     * The {path} is the location of the Paystack CSV.
     * The {--apply} flag determines if we actually update the DB.
     */
    protected $signature = 'settlement:process {path} {--apply}';

    protected $description = 'Process Paystack settlement CSV and move uncleared -> cleared using Wallet model methods.';

    public function handle()
    {
        $path = $this->argument('path');
        $apply = $this->option('apply');

        if (!file_exists($path)) {
            $this->error("CSV file not found: {$path}");
            return Command::FAILURE;
        }

        $this->info("Processing settlement CSV: {$path}");

        // Open and parse CSV
        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->error('Unable to open file');
            return Command::FAILURE;
        }

        $header = null;
        $rows = [];
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (!$header) {
                $header = $data;
                continue;
            }
            $rows[] = array_combine($header, $data);
        }
        fclose($handle);

        foreach ($rows as $row) {
            $reference = $row['reference'] ?? null;
            $currency = $row['currency'] ?? null;
            $amount = isset($row['amount']) ? (float) $row['amount'] : 0;

            if (!$reference || !$currency) {
                Log::warning('Skipping invalid settlement row', $row);
                continue;
            }

            // Wrap each row in a transaction for data integrity
            DB::transaction(function () use ($reference, $currency, $amount, $apply) {
                // 1. Find the Ledger entry
                $ledger = Ledger::where('reference', $reference)
                    ->where('status', 'pending')
                    ->first();

                if (!$ledger) {
                    Log::warning("Ledger entry not found or already settled: {$reference}");
                    return;
                }

                // 2. Find the User and their specific Currency Wallet
                $user = User::find($ledger->user_id);
                if (!$user) {
                    Log::warning("User not found for ledger: {$ledger->user_id}");
                    return;
                }

                // Using your User model helper: fxWallet()
                $wallet = $user->fxWallet($currency);

                if ($apply) {
                    // 3. Perform the settlement using your Wallet model helper
                    // This moves uncleared -> cleared and handles NGN/USD logic
                    $wallet->settle();

                    // 4. Mark Ledger as completed
                    $ledger->update(['status' => 'completed']);

                    $this->info("Successfully settled {$currency} {$amount} for Ref: {$reference}");
                } else {
                    $this->info("[Dry-run] Would settle {$currency} {$amount} for Ref: {$reference}");
                }
            });
        }

        $this->info('Settlement process completed.');
        if (!$apply) {
            $this->warn('NOTE: This was a dry-run. No balances were changed. Use --apply to finalize.');
        }

        return Command::SUCCESS;
    }
}