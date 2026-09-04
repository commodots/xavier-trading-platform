<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedIncomeInvestment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedIncomeInvestmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $investments = FixedIncomeInvestment::query()
            ->where('user_id', $request->user()->id)
            ->with('product')
            ->latest('investment_date')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $investments,
        ]);
    }

    public function show(
        Request $request,
        FixedIncomeInvestment $fixedIncomeInvestment
    ): JsonResponse {
        abort_unless(
            $fixedIncomeInvestment->user_id === $request->user()->id,
            403
        );

        return response()->json([
            'success' => true,
            'data' => $fixedIncomeInvestment->load([
                'product',
                'transactions',
            ]),
        ]);
    }
}