<?php

namespace App\Services\FixedIncome;

use RuntimeException;

class FixedIncomeStateManager
{
    private array $transitions = [
        'pending' => [
            'pending_execution',
            'cancelled',
        ],

        'pending_execution' => [
            'active',
            'rejected',
            'failed',
            'cancelled',
        ],

        'failed' => [
            'pending_execution',
            'cancelled',
        ],

        'active' => [
            'maturing',
            'matured',
        ],

        'maturing' => [
            'matured',
            'failed',
        ],

        'matured' => [
            'redeemed',
        ],

        'redeemed' => [],

        'rejected' => [],

        'cancelled' => [],
    ];

    public function canTransition(
        string $from,
        string $to
    ): bool {
        return in_array(
            $to,
            $this->transitions[$from] ?? [],
            true
        );
    }

    public function transition(
        $investment,
        string $to
    ): void {
        $from = $investment->status;

        if (
            $from === $to
        ) {
            return;
        }

        if (
            ! $this->canTransition(
                $from,
                $to
            )
        ) {
            throw new RuntimeException(
                "Invalid Fixed Income transition: {$from} → {$to}"
            );
        }

        $investment->update([
            'status' => $to,
            'last_status_at' => now(),
        ]);
    }
}