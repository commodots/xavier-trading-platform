<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrokerService
{
    public function fundAccount($user, $usdAmount)
    {
        $response = Http::withToken(config('services.broker.secret'))
            ->post('https://broker-api.com/fund', [
                'user_id' => $user->broker_id,
                'amount' => $usdAmount,
            ]);

        return $response->successful();
    }
}
