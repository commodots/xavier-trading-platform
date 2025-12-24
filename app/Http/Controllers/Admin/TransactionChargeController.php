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
            'transaction_type' => 'required|string',
            'charge_type' => 'required|in:flat,percentage',
            'value' => 'required|numeric',
        ]);
    }

    public function update(Request $request, $id)
    {
        $charge = TransactionCharge::findOrFail($id);
        $charge->update($request->all());
        return $charge;
    }
}
