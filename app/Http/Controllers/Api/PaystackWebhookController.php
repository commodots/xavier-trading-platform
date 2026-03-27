<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Secure Signature Validation
        $signature = $request->header('x-paystack-signature');
        $computedSignature = hash_hmac('sha512', $request->getContent(), config('services.paystack.secret_key'));

        if (! $signature || ! hash_equals($computedSignature, $signature)) {
            Log::warning('Paystack Webhook: Invalid Signature Attempt', [
                'ip' => $request->ip(),
                'header' => $signature,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? '';

        //  Route Events
        switch ($event) {
            case 'charge.success':
                $this->handleSuccessfulPayment($payload['data'] ?? []);
                break;

                
            default:
                Log::info('Paystack Webhook: Unhandled event ignored', ['event' => $event]);
                break;
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function handleSuccessfulPayment(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return;
        }

        // Idempotency Check (Prevent double funding)
        $exists = Ledger::where('reference', $reference)->exists() ||
                 NewTransaction::where('meta->reference', $reference)->exists();

        if ($exists) {
            Log::info('Paystack Webhook: Duplicate reference ignored', ['ref' => $reference]);

            return;
        }

        DB::transaction(function () use ($data, $reference) {
            $user = User::where('email', $data['customer']['email'])->first();
            if (! $user) {
                Log::error('Paystack Webhook: User not found', ['email' => $data['customer']['email']]);

                return;
            }

            $amount = $data['amount'] / 100; // Convert Kobo to Naira
            $planCode = $data['plan']['plan_code'] ?? null;
            $type = 'FUND';

            // Logic: Subscription vs. Regular Funding
            if ($planCode) {
                $plan = SubscriptionPlan::where('paystack_plan_code', $planCode)->first();

                if ($plan) {
                    $user->update([
                        'subscription_tier' => $plan->tier,
                        'subscription_expires_at' => now()->addDays($plan->duration_days),
                    ]);
                    $type = 'SUBSCRIPTION';
                }
            } else {
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id, 'currency' => 'NGN'],
                    ['balance' => 0, 'status' => 'active', 'ngn_cleared' => 0]
                );

                // Assuming your Wallet model has a credit method
                $wallet->increment('ngn_cleared', $amount);
                $type = 'FUND';
            }

            // Audit Trail: Create Ledger & Transaction record
            Ledger::create([
                'user_id' => $user->id,
                'currency' => 'NGN',
                'amount' => $amount,
                'type' => $type,
                'status' => 'completed',
                'reference' => $reference,
                'meta' => $data,
            ]);

            NewTransaction::create([
                'user_id' => $user->id,
                'currency' => 'NGN',
                'amount' => $amount,
                'net_amount' => $amount,
                'type' => ($type === 'SUBSCRIPTION') ? 'subscription' : 'deposit',
                'status' => 'completed',
                'meta' => array_merge($data, ['reference' => $reference]),
            ]);

            Log::info('Paystack Webhook: Payment processed successfully', [
                'user_id' => $user->id,
                'type' => $type,
                'ref' => $reference,
            ]);
        });
    }
}
