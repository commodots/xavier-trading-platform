<?php

namespace App\Services\Reports;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class AccountStatementService
{
    public function generate(User $user, $from, $to, $wallet = 'all')
    {
        // Stock tickers that map to USD
        $stockTickers = ['AAPL', 'TSLA', 'GOOGL', 'MSFT', 'AMZN', 'META', 'NVDA', 'GOOG', 'NFLX', 'INTC'];

        // Build transaction query
        $query = Transaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc');

        // Filter by specific wallet currency
        if ($wallet !== 'all') {
            $query->where(function ($q) use ($wallet, $stockTickers) {
                if ($wallet === 'USD') {
                    $q->where('asset', 'USD')
                      ->orWhere(function ($sub) use ($stockTickers) {
                          foreach ($stockTickers as $i => $ticker) {
                              $method = $i === 0 ? 'where' : 'orWhere';
                              $sub->$method('asset', $ticker);
                          }
                      });
                } else {
                    $q->where('asset', $wallet);
                }
            });
        }

        $transactions = $query->get();

        // Get current wallet balances to use as starting point
        $wallets = Wallet::where('user_id', $user->id)->get();
        $currencyBalances = [];
        foreach ($wallets as $w) {
            $currencyBalances[$w->currency] = (float) $w->balance;
        }

        // Calculate running balance for each transaction (per-currency)
        $openingBalance = 0;
        $ledger = [];
        $totals = []; // Track per-currency totals
        $totalIn = 0;
        $totalOut = 0;

        foreach ($transactions as $transaction) {
            // Determine currency - map stock tickers to USD
            $currency = $transaction->asset ?? 'USD';
            if (in_array($currency, $stockTickers)) {
                $currency = 'USD';
            }
            
            // Get balance for this specific currency before transaction
            $balanceBefore = $currencyBalances[$currency] ?? 0;
            
            // Determine if this is an inflow or outflow
            $isInflow = in_array($transaction->type, ['deposit', 'credit', 'transfer_in']);
            $amount = (float) $transaction->amount;
            
            // Update balance for this currency only
            if ($isInflow) {
                $currencyBalances[$currency] = $balanceBefore + $amount;
                $totalIn += $amount;
            } else {
                $currencyBalances[$currency] = $balanceBefore - $amount;
                $totalOut += $amount;
            }

            $balanceAfter = $currencyBalances[$currency];

            // Track per-currency totals
            $key = $isInflow ? 'in_' . $currency : 'out_' . $currency;
            if (!isset($totals[$key])) {
                $totals[$key] = 0;
            }
            $totals[$key] += $amount;

            // For trades, extract buy/sell from meta
            $tradeDirection = null;
            if ($transaction->type === 'trade' && $transaction->meta) {
                $meta = is_string($transaction->meta) ? json_decode($transaction->meta, true) : $transaction->meta;
                $tradeDirection = $meta['trade_type'] ?? $meta['side'] ?? $meta['direction'] ?? null;
            }

            $ledger[] = [
                'transaction' => $transaction,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'is_inflow' => $isInflow,
                'trade_direction' => $tradeDirection,
            ];
        }

        $closingBalance = array_sum($currencyBalances);
        
        // Set opening balance from the first transaction's balance_before
        $openingBalance = !empty($ledger) ? $ledger[0]['balance_before'] : 0;

        // Get current wallet balances
        $currentBalances = [];
        foreach ($wallets as $w) {
            $currentBalances[$w->currency] = $w->balance;
        }

        return [
            'ledger' => $ledger,
            'summary' => [
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'totals' => $totals,
                'transaction_count' => count($ledger),
            ],
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'current_balances' => $currentBalances,
        ];
    }
}
