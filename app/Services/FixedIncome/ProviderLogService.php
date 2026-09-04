<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProviderLog;

class ProviderLogService
{
    public function log(
        FixedIncomeInvestment $investment,
        string $operation,
        array $data = []
    ): FixedIncomeProviderLog {
        return FixedIncomeProviderLog::create([
            'fixed_income_investment_id' =>
                $investment->id,

            'provider' =>
                $investment->provider,

            'operation' =>
                $operation,

            'request_reference' =>
                $data['request_reference'] ?? null,

            'provider_reference' =>
                $data['provider_reference'] ?? null,

            'http_status' =>
                $data['http_status'] ?? null,

            'status' =>
                $data['status'] ?? 'unknown',

            'request_payload' =>
                $this->sanitize(
                    $data['request_payload'] ?? []
                ),

            'response_payload' =>
                $this->sanitize(
                    $data['response_payload'] ?? []
                ),

            'error_message' =>
                $data['error_message'] ?? null,
        ]);
    }

    private function sanitize(array $data): array
    {
        $sensitive = [
            'authorization',
            'api_key',
            'secret',
            'password',
            'token',
            'private_key',
            'client_secret',
        ];

        foreach ($sensitive as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}