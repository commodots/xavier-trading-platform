<?php

namespace App\Services\Stocks;

use App\Models\Portfolio;
use App\Models\ProviderAccount;
use App\Providers\AlpacaProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AlpacaPortfolioSyncService
{
    public function __construct(
        protected AlpacaProvider $provider
    ) {}

    public function portfolioForUser(int $userId): array
    {
        $account = ProviderAccount::query()
            ->where('user_id', $userId)
            ->where('provider', 'alpaca')
            ->where('status', 'active')
            ->first();

        if (! $account) {
            throw new RuntimeException(
                'No active Alpaca account is mapped to this user.'
            );
        }

        $this->sync($account);

        return Portfolio::query()
            ->where('user_id', $userId)
            ->where('category', 'foreign')
            ->get()
            ->all();
    }

    public function sync(ProviderAccount $account): int
    {
        $this->assertDedicatedAccount($account);
        $positions = $this->provider->positions();

        if ($positions === []) {
            return 0;
        }

        return DB::transaction(function () use ($account, $positions): int {
            $syncedSymbols = [];

            foreach ($positions as $position) {
                $symbol = strtoupper(trim((string) ($position['symbol'] ?? '')));

                if ($symbol === '') {
                    continue;
                }

                $syncedSymbols[] = $symbol;
                $portfolio = Portfolio::firstOrNew([
                    'user_id' => $account->user_id,
                    'symbol' => $symbol,
                ]);
                $quantity = max(0, (float) ($position['qty'] ?? 0));
                $uncleared = min(
                    $quantity,
                    max(0, (float) $portfolio->uncleared_quantity)
                );

                $portfolio->fill([
                    'name' => $position['name'] ?? $portfolio->name ?? $symbol,
                    'category' => 'foreign',
                    'currency' => 'USD',
                    'quantity' => $quantity,
                    'cleared_quantity' => max(0, $quantity - $uncleared),
                    'uncleared_quantity' => $uncleared,
                    'avg_price' => (float) ($position['avg_entry_price'] ?? 0),
                    'market_price' => (float) ($position['current_price'] ?? 0),
                ])->save();
            }

            Portfolio::query()
                ->where('user_id', $account->user_id)
                ->where('category', 'foreign')
                ->whereNotIn('symbol', $syncedSymbols)
                ->update([
                    'quantity' => 0,
                    'cleared_quantity' => 0,
                    'uncleared_quantity' => 0,
                ]);

            return count($syncedSymbols);
        });
    }

    protected function assertDedicatedAccount(ProviderAccount $account): void
    {
        if ($account->provider !== 'alpaca'
            || $account->status !== 'active'
            || blank($account->market_account_id)) {
            throw new RuntimeException(
                'An active Alpaca provider account ID is required for portfolio sync.'
            );
        }

        $accountKey = $account->metadata['alpaca_account_id']
            ?? $account->market_account_id;
        $sharedAccounts = ProviderAccount::query()
            ->where('provider', 'alpaca')
            ->where('status', 'active')
            ->get()
            ->filter(function (ProviderAccount $candidate) use ($accountKey): bool {
                $candidateKey = $candidate->metadata['alpaca_account_id']
                    ?? $candidate->market_account_id;

                return (string) $candidateKey === (string) $accountKey;
            });

        if ($sharedAccounts->pluck('user_id')->unique()->count() > 1) {
            throw new RuntimeException(
                'Shared Alpaca accounts require internal allocation ledgers and cannot be synced by user.'
            );
        }
    }
}
