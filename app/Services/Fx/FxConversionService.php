<?php

namespace App\Services\Fx;

use App\Models\FxConversion;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FxConversionService
{
    public function __construct(
        protected FxService $fxService
    ) {}

    public function convert(int $userId, string $fromCurrency, string $toCurrency, float $amount): array
    {
        return DB::transaction(function () use ($userId, $fromCurrency, $toCurrency, $amount) {
            // Get wallets
            $fromWallet = Wallet::where('user_id', $userId)
                ->where('currency', $fromCurrency)
                ->firstOrFail();

            $toWallet = Wallet::where('user_id', $userId)
                ->where('currency', $toCurrency)
                ->firstOrFail();

            // Check balance
            $clearedBalance = $fromWallet->getClearedBalance();
            if ($clearedBalance < $amount) {
                throw new \Exception("Insufficient {$fromCurrency} balance. Available: {$clearedBalance}, Needed: {$amount}");
            }

            // Get quote from active provider
            $quote = $this->fxService->quote($fromCurrency, $toCurrency, $amount);
            $convertedAmount = $quote['receive_amount'];
            $rate = $quote['rate'];
            $provider = $quote['provider'];

            // Create reference
            $reference = 'FX-' . strtoupper(Str::random(12));

            // Create FX conversion record
            $conversion = FxConversion::create([
                'user_id' => $userId,
                'provider' => $provider,
                'reference' => $reference,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'amount' => $amount,
                'rate' => $rate,
                'converted_amount' => $convertedAmount,
                'status' => 'completed',
            ]);

            // Debit source wallet
            $fromWallet->debit($amount, 'cleared');
            $fromWallet->save();

            // Credit destination wallet
            $toWallet->credit($convertedAmount, 'cleared');
            $toWallet->save();

            // Record debit transaction
            WalletTransaction::create([
                'user_id' => $userId,
                'wallet_currency' => $fromCurrency,
                'type' => 'FX_DEBIT',
                'amount' => $amount,
                'reference' => $reference,
                'note' => "FX Conversion: {$fromCurrency} to {$toCurrency} @ {$rate}",
            ]);

            // Record credit transaction
            WalletTransaction::create([
                'user_id' => $userId,
                'wallet_currency' => $toCurrency,
                'type' => 'FX_CREDIT',
                'amount' => $convertedAmount,
                'reference' => $reference,
                'note' => "FX Conversion: {$fromCurrency} to {$toCurrency} @ {$rate}",
            ]);

            // Record in ledger
            Ledger::create([
                'type' => 'FX_CONVERSION',
                'amount' => $amount,
                'currency' => $fromCurrency,
                'user_id' => $userId,
                'reference' => $reference,
                'meta' => [
                    'description' => "FX Conversion {$fromCurrency}->{$toCurrency} via {$provider}",
                    'to_currency' => $toCurrency,
                    'converted_amount' => $convertedAmount,
                    'rate' => $rate,
                ],
            ]);

            return [
                'success' => true,
                'reference' => $reference,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'amount' => $amount,
                'rate' => $rate,
                'converted_amount' => $convertedAmount,
                'provider' => $provider,
            ];
        });
    }

    public function quote(int $userId, string $fromCurrency, string $toCurrency, float $amount): array
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('currency', $fromCurrency)
            ->firstOrFail();

        $quote = $this->fxService->quote($fromCurrency, $toCurrency, $amount);

        return array_merge($quote, [
            'available_balance' => $wallet->getClearedBalance(),
            'sufficient' => $wallet->getClearedBalance() >= $amount,
        ]);
    }

    public function getHistory(int $userId, int $limit = 20): array
    {
        $conversions = FxConversion::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();

        return $conversions;
    }
}