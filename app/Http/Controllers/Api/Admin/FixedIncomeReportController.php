<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\FixedIncome\FixedIncomeReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedIncomeReportController extends Controller
{
    public function investments(
        Request $request,
        FixedIncomeReportService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->investments(
                $request->only([
                    'status',
                    'product_id',
                    'currency',
                    'execution_mode',
                    'provider',
                    'date_from',
                    'date_to',
                ])
            ),
        ]);
    }
}