<?php

namespace App\Services\CSL;

class CslHealthService
{
    public function __construct(
        protected CslTradeXClient $xt
    ) {}

    public function check(): array
    {
        try {

            $response =
                $this->xt->marketStatus();

            return [
                'connected' => true,
                'status' => 'ok',
                'response' => $response,
            ];

        } catch (\Throwable $e) {

            return [
                'connected' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
