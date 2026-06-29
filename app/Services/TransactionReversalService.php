<?php

namespace App\Services;

use App\Models\NewTransaction;
use App\Models\Wallet;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class TransactionReversalService
{
    public function reverseTransaction(NewTransaction $transaction, ?string $reason = null): bool
    {
        if ($transaction->status === 'reversed') {
            return false;
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $oldValues = $transaction->toArray();
            
            $transaction->update([
                'status' => 'reversed',
                'meta' => array_merge($transaction->meta ?? [], [
                    'reversal_reason' => $reason,
                    'reversed_at' => now()->toDateTimeString(),
                ]),
            ]);

            // Refund wallet if it was a withdrawal
            if ($transaction->type === 'withdrawal') {
                $wallet = Wallet::where('user_id', $transaction->user_id)
                    ->where('currency', $transaction->currency)
                    ->first();

                if ($wallet) {
                    $col = $transaction->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
                    $wallet->increment($col, $transaction->amount);
                    $wallet->increment('balance', $transaction->amount);
                    $wallet->refreshBalance();
                }
            }

            // Log audit trail for transaction reversal
            AuditService::log(
                'transaction_reversed',
                'transaction',
                $transaction->id,
                $oldValues,
                $transaction->fresh()->toArray()
            );

            return true;
        });
    }
}