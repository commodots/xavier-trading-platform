<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionType;
use Illuminate\Http\Request;

class TransactionTypeController extends Controller
{
    
    public function index()
    {
        return response()->json(TransactionType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:transaction_types',
            'category' => 'required|string',
            'active' => 'boolean'
        ]);

        $type = TransactionType::create($validated);

        return response()->json($type, 201);
    }

    
    public function update(Request $request, TransactionType $transactionType)
    {
        $validated = $request->validate([
            'name' => 'string|unique:transaction_types,name,' . $transactionType->id,
            'category' => 'string',
            'active' => 'boolean'
        ]);

        $transactionType->update($validated);

        return response()->json($transactionType);
    }

   
    public function destroy(TransactionType $transactionType)
    {
        $transactionType->delete();
        return response()->json(null, 204);
    }
}