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
        DB::transaction(function () use ($data) {
            $email = $data['customer']['email'] ?? null;

            if (! $email) {
                Log::warning('Paystack webhook missing customer email', ['data' => $data]);

                return;
            }

            $user = User::where('email', $email)->first();
            if (! $user) {
                Log::warning('Paystack webhook user not found', ['email' => $email]);

                return;
            }

            $amount = ($data['amount'] ?? 0) / 100;

            // Credit NGN as uncleared
            $wallet = $user->fxWallet('NGN');
            $wallet->increment('ngn_uncleared', $amount);

            Ledger::create([
                'user_id' => $user->id,
                'currency' => 'NGN',
                'amount' => $amount,
                'type' => 'FUND',
                'status' => 'pending',
                'reference' => $data['reference'] ?? null,
                'meta' => $data,
            ]);
        });
    }
}
