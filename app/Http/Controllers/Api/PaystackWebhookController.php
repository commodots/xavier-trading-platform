<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\NewTransaction;
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

    // Idempotency Check
    if (Ledger::where('reference', $reference)->exists() || 
        NewTransaction::where('meta->reference', $reference)->exists()) {
        Log::info('Paystack webhook: Duplicate reference ignored', ['ref' => $reference]);
        return;
    }

    DB::transaction(function () use ($data, $reference) {
        $user = User::where('email', $data['customer']['email'])->first();
        if (!$user) return;

        $amount = $data['amount'] / 100;
        $planCode = $data['plan']['plan_code'] ?? null;
        $type = 'FUND'; // Default type

        if ($planCode) {
            // Handle Subscription Logic
            $plan = \App\Models\SubscriptionPlan::where('paystack_plan_code', $planCode)->first();
            
            if ($plan) {
                $user->update([
                    'subscription_tier' => $plan->tier,
                    'subscription_expires_at' => now()->addDays($plan->duration_days),
                ]);
                $type = 'SUBSCRIPTION';
            }
        } else {
            // Handle Regular Wallet Funding
            $wallet = \App\Models\Wallet::firstOrCreate(
                ['user_id' => $user->id, 'currency' => 'NGN'],
                ['balance' => 0, 'status' => 'active', 'ngn_cleared' => 0, 'ngn_uncleared' => 0, 'locked' => 0]
            );
            $wallet->credit($amount, 'cleared');
            $type = 'FUND';
        }

        // Record Ledger Entry
        Ledger::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'amount' => $amount,
            'type' => $type,
            'status' => 'completed',
            'reference' => $reference,
            'meta' => $data,
        ]);

        // Record Transaction Entry
        $metaData = $data;
        $metaData['reference'] = $reference;

        NewTransaction::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'amount' => $amount,
            'net_amount' => $amount, 
            'type' => ($type === 'SUBSCRIPTION') ? 'subscription' : 'deposit', 
            'status' => 'completed',
            'meta' => $metaData, 
        ]);
    });
}
}