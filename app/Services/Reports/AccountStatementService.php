<?php

namespace App\Services\Reports;

use App\Models\Transaction;
use App\Models\User;

class AccountStatementService
{
    public function generate(User $user, $from, $to)
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get();
    }
}