<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verify signature
        $signature = hash_hmac('sha512', $request->getContent(), config('services.paystack.secret_key'));

        if (! hash_equals($signature, $request->header('x-paystack-signature', ''))) {
            Log::warning('Invalid Paystack signature', ['headers' => $request->headers->all()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->all();

        if (($event['event'] ?? '') === 'charge.success') {
            $this->handleSuccessfulPayment($event['data'] ?? []);
        }

        return response()->json(['status' => 'ok']);
    }

    
private function handleSuccessfulPayment(array $data): void
{
    $reference = $data['reference'];

   
    if (Ledger::where('reference', $reference)->exists()) {
        Log::info('Paystack webhook: Duplicate reference ignored', ['ref' => $reference]);
        return;
    }

    DB::transaction(function () use ($data, $reference) {
        $user = User::where('email', $data['customer']['email'])->first();
        if (!$user) return;

        $amount = $data['amount'] / 100;
        $wallet = $user->fxWallet('NGN'); 

        $wallet->credit($amount, 'uncleared');

        Ledger::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'amount' => $amount,
            'type' => 'FUND',
            'status' => 'pending',
            'reference' => $reference,
            'meta' => $data,
        ]);
    });
}
}
