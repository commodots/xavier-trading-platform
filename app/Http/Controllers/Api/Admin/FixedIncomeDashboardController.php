<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\FixedIncome\FixedIncomeDashboardService;
use Illuminate\Http\JsonResponse;

class FixedIncomeDashboardController extends Controller
{
    public function index(
        FixedIncomeDashboardService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->summary(),
        ]);
    }
}