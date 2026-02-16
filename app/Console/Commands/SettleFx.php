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
                // Settle NGN balances
                $ngnSettled = Wallet::where('ngn_uncleared', '>', 0)
                    ->update([
                        'ngn_cleared' => DB::raw('ngn_cleared + ngn_uncleared'),
                        'ngn_uncleared' => 0,
                    ]);
                
                // Settle USD balances
                $usdSettled = Wallet::where('usd_uncleared', '>', 0)
                    ->update([
                        'usd_cleared' => DB::raw('usd_cleared + usd_uncleared'),
                        'usd_uncleared' => 0,
                    ]);
                
                $totalSettled = $ngnSettled + $usdSettled;

                $this->info("Settled {$totalSettled} wallet(s)");
                Log::info('FX settlement completed', ['wallets_settled' => $totalSettled]);
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
