<?php

namespace App\Services\Fx;

use App\Models\FxConversion;
use App\Models\Ledger;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FxConversionService
{
    public function __construct(
        protected FxService $fxService
    ) {}

    public function convert(int $userId, string $fromCurrency, string $toCurrency, float $amount, string $idempotencyKey): array
    {
        $existing = FxConversion::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            $this->assertSameRequest($existing, $fromCurrency, $toCurrency, $amount);

            if ($existing->provider === 'manual' && $existing->status === 'pending') {
                return $this->finalize($existing, $existing->response['quote'] ?? []);
            }

            return $this->toResult($existing);
        }

        $provider = $this->fxService->provider();
        $quote = $provider->quote($fromCurrency, $toCurrency, $amount);
        if ((float) ($quote['rate'] ?? 0) <= 0 || (float) ($quote['receive_amount'] ?? 0) <= 0) {
            throw new \RuntimeException('FX provider returned an invalid quote.');
        }

        $reference = 'FX-'.strtoupper(Str::random(16));
        $created = false;
        $conversion = DB::transaction(function () use ($userId, $fromCurrency, $toCurrency, $amount, $idempotencyKey, $quote, $reference, &$created) {
            $existing = FxConversion::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->assertSameRequest($existing, $fromCurrency, $toCurrency, $amount);

                return $existing;
            }

            $fromWallet = Wallet::query()
                ->where('user_id', $userId)
                ->where('currency', $fromCurrency)
                ->lockForUpdate()
                ->firstOrFail();
            Wallet::query()
                ->where('user_id', $userId)
                ->where('currency', $toCurrency)
                ->lockForUpdate()
                ->firstOrFail();

            $clearedBalance = $fromWallet->getClearedBalance();
            if ($clearedBalance < $amount) {
                throw new \RuntimeException("Insufficient {$fromCurrency} balance. Available: {$clearedBalance}, Needed: {$amount}");
            }

            $fromWallet->reserve($amount);
            $fromWallet->save();

            $created = true;

            return FxConversion::create([
                'user_id' => $userId,
                'provider' => $quote['provider'],
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'amount' => $amount,
                'rate' => $quote['rate'],
                'converted_amount' => $quote['receive_amount'],
                'status' => $quote['provider'] === 'manual' ? 'pending' : 'processing',
                'response' => [
                    'quote' => $quote,
                    'quote_reference' => $quote['reference'] ?? null,
                ],
            ]);
        });

        if (! $created) {
            return $this->toResult($conversion);
        }

        if ($conversion->provider === 'manual') {
            return $this->finalize($conversion, [
                'status' => 'completed',
                'rate' => $quote['rate'],
                'converted_amount' => $quote['receive_amount'],
                'reference' => $reference,
            ]);
        }

        try {
            $providerResult = $provider->convert($fromCurrency, $toCurrency, $amount, $reference, $quote);
        } catch (Throwable $exception) {
            $conversion->update([
                'status' => 'unknown',
                'response' => array_merge($conversion->response ?? [], [
                    'error' => $exception->getMessage(),
                    'outcome_unknown_at' => now()->toIso8601String(),
                ]),
            ]);
            Log::warning('FX provider outcome is unresolved; conversion will not be retried automatically.', [
                'reference' => $reference,
                'provider' => $conversion->provider,
                'error' => $exception->getMessage(),
            ]);

            return $this->toResult($conversion->fresh());
        }

        $conversion->update([
            'rate' => $providerResult['rate'] ?? $conversion->rate,
            'converted_amount' => $providerResult['converted_amount'] ?? $conversion->converted_amount,
            'response' => array_merge($conversion->response ?? [], $providerResult),
        ]);

        if (($providerResult['status'] ?? 'processing') === 'completed') {
            return $this->finalize($conversion->fresh(), $providerResult);
        }

        if (($providerResult['status'] ?? null) === 'failed') {
            return $this->fail($conversion->fresh(), $providerResult);
        }

        if (($providerResult['status'] ?? null) === 'unknown') {
            $conversion->update(['status' => 'unknown']);

            return $this->toResult($conversion->fresh());
        }

        $conversion->update(['status' => 'processing']);

        return $this->toResult($conversion->fresh());
    }

    public function completeProviderConversion(string $reference, array $providerData = []): ?array
    {
        $conversion = FxConversion::query()
            ->where('reference', $reference)
            ->orWhere('response->quote_reference', $reference)
            ->orWhere('response->provider_reference', $reference)
            ->lockForUpdate()
            ->first();

        if (! $conversion) {
            return null;
        }

        return $this->finalize($conversion, array_merge($conversion->response ?? [], $providerData));
    }

    public function failProviderConversion(string $reference, array $providerData = []): ?array
    {
        $conversion = FxConversion::query()
            ->where('reference', $reference)
            ->orWhere('response->quote_reference', $reference)
            ->orWhere('response->provider_reference', $reference)
            ->first();

        return $conversion ? $this->fail($conversion, $providerData) : null;
    }

    public function markProviderProcessing(FxConversion $conversion, array $providerData = []): array
    {
        return DB::transaction(function () use ($conversion, $providerData) {
            $conversion = FxConversion::query()->lockForUpdate()->findOrFail($conversion->id);
            if (in_array($conversion->status, ['completed', 'failed'], true)) {
                return $this->toResult($conversion);
            }

            $conversion->update([
                'status' => 'processing',
                'response' => array_merge($conversion->response ?? [], $providerData),
            ]);

            return $this->toResult($conversion->fresh());
        });
    }

    private function finalize(FxConversion $conversion, array $providerResult): array
    {
        return DB::transaction(function () use ($conversion, $providerResult) {
            $conversion = FxConversion::query()->lockForUpdate()->findOrFail($conversion->id);
            if ($conversion->status === 'completed') {
                return $this->toResult($conversion);
            }
            if ($conversion->status === 'failed') {
                return $this->toResult($conversion);
            }

            $convertedAmount = (float) ($providerResult['converted_amount'] ?? $conversion->converted_amount);
            $rate = (float) ($providerResult['rate'] ?? $conversion->rate);
            if ($convertedAmount <= 0 || $rate <= 0) {
                throw new \RuntimeException('FX provider returned invalid conversion values.');
            }

            $fromWallet = Wallet::query()
                ->where('user_id', $conversion->user_id)
                ->where('currency', $conversion->from_currency)
                ->lockForUpdate()
                ->firstOrFail();
            $toWallet = Wallet::query()
                ->where('user_id', $conversion->user_id)
                ->where('currency', $conversion->to_currency)
                ->lockForUpdate()
                ->firstOrFail();

            $fromWallet->consumeReservation((float) $conversion->amount);
            $toWallet->credit($convertedAmount, 'cleared');
            $fromWallet->save();
            $toWallet->save();

            $note = "FX Conversion: {$conversion->from_currency} to {$conversion->to_currency} @ {$rate}";
            WalletTransaction::firstOrCreate(
                ['user_id' => $conversion->user_id, 'reference' => $conversion->reference, 'type' => 'FX_DEBIT'],
                ['wallet_currency' => $conversion->from_currency, 'amount' => $conversion->amount, 'note' => $note]
            );
            WalletTransaction::firstOrCreate(
                ['user_id' => $conversion->user_id, 'reference' => $conversion->reference, 'type' => 'FX_CREDIT'],
                ['wallet_currency' => $conversion->to_currency, 'amount' => $convertedAmount, 'note' => $note]
            );
            Ledger::firstOrCreate(
                ['reference' => $conversion->reference, 'type' => 'FX_CONVERSION'],
                [
                    'user_id' => $conversion->user_id,
                    'amount' => $conversion->amount,
                    'currency' => $conversion->from_currency,
                    'meta' => [
                        'to_currency' => $conversion->to_currency,
                        'converted_amount' => $convertedAmount,
                        'rate' => $rate,
                        'provider' => $conversion->provider,
                    ],
                ]
            );

            $conversion->update([
                'rate' => $rate,
                'converted_amount' => $convertedAmount,
                'status' => 'completed',
                'response' => array_merge($conversion->response ?? [], $providerResult),
            ]);

            return $this->toResult($conversion->fresh());
        });
    }

    private function fail(FxConversion $conversion, array $providerResult): array
    {
        return DB::transaction(function () use ($conversion, $providerResult) {
            $conversion = FxConversion::query()->lockForUpdate()->findOrFail($conversion->id);
            if (in_array($conversion->status, ['completed', 'failed'], true)) {
                return $this->toResult($conversion);
            }

            $wallet = Wallet::query()
                ->where('user_id', $conversion->user_id)
                ->where('currency', $conversion->from_currency)
                ->lockForUpdate()
                ->firstOrFail();
            $wallet->releaseReservation((float) $conversion->amount);
            $wallet->save();

            $conversion->update([
                'status' => 'failed',
                'response' => array_merge($conversion->response ?? [], $providerResult),
            ]);

            return $this->toResult($conversion->fresh());
        });
    }

    private function toResult(FxConversion $conversion): array
    {
        return [
            'success' => $conversion->status === 'completed',
            'status' => $conversion->status,
            'reference' => $conversion->reference,
            'from_currency' => $conversion->from_currency,
            'to_currency' => $conversion->to_currency,
            'amount' => $conversion->amount,
            'rate' => $conversion->rate,
            'converted_amount' => $conversion->converted_amount,
            'provider' => $conversion->provider,
        ];
    }

    private function assertSameRequest(
        FxConversion $conversion,
        string $fromCurrency,
        string $toCurrency,
        float $amount
    ): void {
        if (
            $conversion->from_currency !== $fromCurrency ||
            $conversion->to_currency !== $toCurrency ||
            abs((float) $conversion->amount - $amount) >= 0.000001
        ) {
            throw new \InvalidArgumentException(
                'This idempotency key was already used for a different FX conversion.'
            );
        }
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
