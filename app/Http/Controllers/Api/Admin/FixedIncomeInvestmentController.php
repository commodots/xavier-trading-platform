<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FixedIncomeInvestment;
use App\Services\FixedIncome\FixedIncomeMaturityService;
use App\Services\FixedIncome\FixedIncomeReinvestmentService;
use App\Services\FixedIncomeLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedIncomeInvestmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FixedIncomeInvestment::with([
            'user:id,name,email',
            'product:id,name,code,currency',
        ]);

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('currency')) {
        $query->where(
            'currency',
            $request->string('currency')
        );
    }
    if ($request->filled('execution_mode')) {
        $query->where(
            'execution_mode',
            $request->string('execution_mode')
        );
    }
    if ($request->filled('provider')) {
        $query->where(
            'provider',
            $request->string('provider')
        );
    }
    if ($request->filled('date_from')) {
        $query->whereDate(
            'investment_date',
            '>=',
            $request->date('date_from')
        );
    }
    if ($request->filled('date_to')) {
        $query->whereDate(
            'investment_date',
            '<=',
            $request->date('date_to')
        );
    }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where(
                    'reference',
                    'like',
                    "%{$search}%"
                )
                    ->orWhereHas('user', function ($user) use ($search) {
                        $user->where(
                            'name',
                            'like',
                            "%{$search}%"
                        );
                    });
            });
        }

        $investments = $query
            ->latest('id')
            ->paginate(
                min(
                    (int) $request->get(
                        'per_page',
                        25
                    ),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $investments,
        ]);
    }

    public function show(
        FixedIncomeInvestment $fixedIncomeInvestment
    ): JsonResponse {

        return response()->json([
            'success' => true,
            'data' => $fixedIncomeInvestment->load([
                'user',
                'product',
                'transactions',
            ]),
        ]);
    }

    public function activate(
        Request $request,
        FixedIncomeInvestment $fixedIncomeInvestment,
        FixedIncomeLifecycleService $lifecycle
    ): JsonResponse {

        $validated = $request->validate([
            'provider_reference' => 'nullable|string|max:255',
        ]);

        $investment = $lifecycle->activate(
            $fixedIncomeInvestment,
            $validated['provider_reference'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Investment activated successfully.',
            'data' => $investment,
        ]);
    }

    public function reject(
        Request $request,
        FixedIncomeInvestment $fixedIncomeInvestment,
        FixedIncomeLifecycleService $lifecycle
    ): JsonResponse {

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $investment = $lifecycle->reject(
            $fixedIncomeInvestment,
            $validated['reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Investment rejected and funds returned.',
            'data' => $investment,
        ]);
    }

    public function mature(
        FixedIncomeInvestment $fixedIncomeInvestment
    ): JsonResponse {
        try {
            $investment = app(
                FixedIncomeMaturityService::class
            )->mature($fixedIncomeInvestment);

            return response()->json([
                'success' => true,
                'message' => 'Fixed Income investment matured successfully.',
                'data' => $investment,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reinvest(
        FixedIncomeInvestment $fixedIncomeInvestment,
        FixedIncomeReinvestmentService $reinvestment
    ): JsonResponse {
        try {
            $investment = $reinvestment->reinvest($fixedIncomeInvestment);

            return response()->json([
                'success' => true,
                'message' => 'Investment reinvested successfully.',
                'data' => $investment,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
