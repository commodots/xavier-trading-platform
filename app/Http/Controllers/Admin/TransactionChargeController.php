<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionCharge;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionChargeController extends Controller
{
    public function store(Request $request)
    {
        $charge = TransactionCharge::updateOrCreate(
            ['transaction_type' => $request->transaction_type],
            $request->only(['percentage', 'flat_fee'])
        );
        return response()->json($charge);
    }

    public function earnings()
    {
        $earnings = Transaction::select('type', DB::raw('SUM(platform_fee) as total_earnings'))
            ->groupBy('type')
            ->get();

        return response()->json($earnings);
    }
}
