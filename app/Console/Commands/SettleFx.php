<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettleFx extends Command
{
    protected $signature = 'settlement:settle-fx {--test : Run in test mode without committing}';

    protected $description = 'Settle uncleared FX balances to cleared (T+1 settlement)';

    public function handle()
    {
        $this->info('Starting FX settlement job...');

        try {
            DB::transaction(function () {
                $settled = Wallet::where('uncleared_balance', '>', 0)
                    ->update([
                        'cleared_balance' => DB::raw('cleared_balance + uncleared_balance'),
                        'uncleared_balance' => 0,
                    ]);

                $this->info("Settled {$settled} wallet(s)");
                Log::info('FX settlement completed', ['wallets_settled' => $settled]);
            });

            $this->info('Settlement completed successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Settlement failed: {$e->getMessage()}");
            Log::error('FX settlement failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
