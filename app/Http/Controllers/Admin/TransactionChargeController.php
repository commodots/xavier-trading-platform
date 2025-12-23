<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionCharge;
use App\Models\NewTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionChargeController extends Controller
{
    public function index()
    {
        return response()->json(TransactionCharge::all());
    }
   public function store(Request $request)
    {
        $request->validate([
            'transaction_type' => 'required|unique:transaction_charges',
            'percentage_fee' => 'required|numeric',
            'flat_fee' => 'required|numeric',
        ]);

        $charge = TransactionCharge::create($request->all());
        return response()->json($charge);
    }

    public function update(Request $request, $id)
    {
        $charge = TransactionCharge::findOrFail($id);
        $charge->update($request->only(['percentage_fee', 'flat_fee']));
        return response()->json($charge);
    }
}
