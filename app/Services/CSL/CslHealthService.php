<?php

namespace App\Services\CSL;

class CslHealthService
{
    public function __construct(
        protected CslClient $client,
        protected CslStockClient $st,
        protected CslTradeXClient $xt
    ) {}

    public function check(): array
    {
        $checks = [
            'credentials' => false,
            'oauth' => false,
            'st' => false,
            'xt' => false,
        ];

        if ($this->client->isMockEnabled()) {
            return [
                'connected' => true,
                'status' => 'ok',
                'mock' => true,
                'checks' => [
                    'credentials' => true,
                    'oauth' => true,
                    'st' => true,
                    'xt' => true,
                ],
                'market_status' => [],
            ];
        }

        $checks['credentials'] = filled(config('services.csl.client_id'))
            && filled(config('services.csl.client_secret'));

        if ($checks['credentials']) {
            try {
                $checks['oauth'] = filled($this->client->getAccessToken());
            } catch (\Throwable) {
                $checks['oauth'] = false;
            }
        }

        if ($checks['oauth']) {
            try {
                $checks['st'] = is_array($this->st->markets());
            } catch (\Throwable) {
                $checks['st'] = false;
            }

            try {
                $marketStatus = $this->xt->marketStatus();
                $checks['xt'] = true;
            } catch (\Throwable) {
                $marketStatus = [];
                $checks['xt'] = false;
            }
        } else {
            $marketStatus = [];
        }

        return [
            'connected' => ! in_array(false, $checks, true),
            'status' => ! in_array(false, $checks, true) ? 'ok' : 'error',
            'mock' => false,
            'checks' => $checks,
            'market_status' => $marketStatus,
        ];
    }
}
