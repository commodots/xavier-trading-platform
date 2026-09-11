<?php

namespace App\Services\CSL;

use App\Models\ProviderAccount;
use Illuminate\Support\Facades\DB;

class CslAccountService
{
    public function __construct(
        protected CslStockClient $st
    ) {}

    public function syncForUser(
        int $userId,
        string $customerId
    ): array {

        $accounts = $this->st->marketAccounts(
            $customerId
        );

        $rows = $this->extractRows($accounts);

        $saved = [];

        return DB::transaction(function () use ($rows, $userId, $customerId, &$saved): array {
            foreach ($rows as $row) {
                $marketAccountId = $row['market_account_id'] ?? null;

                if (! $marketAccountId) {
                    continue;
                }

                $account = ProviderAccount::updateOrCreate(
                    [
                        'provider' => 'csl',
                        'market_account_id' => $marketAccountId,
                    ],
                    [
                        'user_id' => $userId,

                        'customer_id' => $row['customer_id']
                            ?? $customerId,

                        'market_id' => $row['market_id']
                            ?? null,

                        'product_id' => $row['product_id']
                            ?? null,

                        'market_customer_id' => $row['market_customer_id']
                            ?? null,

                        'portfolio_id' => $row['portfolio_id']
                            ?? null,

                        'cash_funding_account_id' => $row['cash_funding_account_id']
                            ?? null,

                        'status' => $row['trading_status']
                            ?? 'active',

                        'metadata' => $row,
                    ]
                );

                $saved[] = $account;
            }

            return $saved;
        });
    }

    protected function extractRows(array $response): array
    {
        foreach ([
            'GetCRSTMarketAccounts',
            'market_accounts',
            'result',
            'data',
        ] as $key) {

            if (
                isset($response[$key])
                && is_array($response[$key])
            ) {
                return $response[$key];
            }
        }

        return [];
    }
}
