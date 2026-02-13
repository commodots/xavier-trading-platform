<?php

namespace App\Console\Commands;

use App\Models\Ledger;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSettlement extends Command
{
    protected $signature = 'settlement:process {path} {--apply}';

    protected $description = 'Process Paystack settlement CSV and move uncleared -> cleared. Use --apply to actually change wallets.';

    public function handle()
    {
        $path = $this->argument('path');
        $apply = $this->option('apply');

        if (! file_exists($path)) {
            $this->error('CSV file not found: '.$path);

            return Command::FAILURE;
        }

        $this->info('Processing settlement CSV: '.$path);

        $handle = fopen($path, 'r');
        if (! $handle) {
            $this->error('Unable to open file');

            return Command::FAILURE;
        }

        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (! $header) {
                $header = $data;

                continue;
            }

            $rows[] = array_combine($header, $data);
        }

        fclose($handle);

        foreach ($rows as $row) {
            // Expecting at least reference,currency,amount
            $reference = $row['reference'] ?? null;
            $currency = $row['currency'] ?? null;
            $amount = isset($row['amount']) ? (float) $row['amount'] : 0;

            $this->info("Row: reference={$reference} currency={$currency} amount={$amount}");

            if (! $reference || ! $currency) {
                Log::warning('Skipping invalid settlement row', $row);

                continue;
            }

            DB::transaction(function () use ($reference, $currency, $amount, $apply) {
                $ledger = Ledger::where('reference', $reference)->where('status', 'pending')->first();
                if (! $ledger) {
                    Log::warning('Ledger entry for settlement not found or not pending', ['reference' => $reference]);

                    return;
                }

                $userId = $ledger->user_id;
                $wallet = Wallet::where('user_id', $userId)->where('currency', $currency)->first();
                if (! $wallet) {
                    Log::warning('User wallet not found for settlement', ['user_id' => $userId, 'currency' => $currency]);

                    return;
                }

                if ($apply) {
                    // Move uncleared -> cleared for given currency
                    if ($currency === 'USD') {
                        $inc = $wallet->usd_uncleared ?? 0;
                        if ($inc <= 0) {
                            Log::info('No USD uncleared to settle', ['wallet_id' => $wallet->id]);

                            return;
                        }
                        $wallet->increment('usd_cleared', $inc);
                        $wallet->decrement('usd_uncleared', $inc);
                    } elseif ($currency === 'NGN') {
                        $inc = $wallet->ngn_uncleared ?? 0;
                        if ($inc <= 0) {
                            Log::info('No NGN uncleared to settle', ['wallet_id' => $wallet->id]);

                            return;
                        }
                        $wallet->increment('ngn_cleared', $inc);
                        $wallet->decrement('ngn_uncleared', $inc);
                    }

                    // Mark ledger as completed
                    $ledger->status = 'completed';
                    $ledger->save();
                } else {
                    // Dry-run: log what would happen
                    Log::info('Settlement dry-run (no changes applied)', ['reference' => $reference, 'currency' => $currency, 'amount' => $amount]);
                }
            });
        }

        $this->info('Settlement processing completed. Use --apply to execute changes.');

        return Command::SUCCESS;
    }
}
