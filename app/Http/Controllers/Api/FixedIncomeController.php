<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedIncomeProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedIncomeController extends Controller
{
    /**
     * List products available to users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FixedIncomeProduct::query()
            ->where('status', 'active');

        if ($request->filled('currency')) {
            $query->where(
                'currency',
                strtoupper($request->currency)
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->type
            );
        }

        $products = $query
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Show a single available product.
     */
    public function show(
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        if ($fixedIncomeProduct->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This Fixed Income product is not currently available.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $fixedIncomeProduct,
        ]);
    }

    /**
     * Calculate expected investment return.
     *
     * This does NOT create an investment.
     */
    public function calculate(
        Request $request,
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        if ($fixedIncomeProduct->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This Fixed Income product is not currently available.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);

        $amount = (float) $validated['amount'];

        if (! $fixedIncomeProduct->acceptsAmount($amount)) {
            $maximum = $fixedIncomeProduct->hasMaximumAmount()
                ? number_format(
                    $fixedIncomeProduct->maximum_amount,
                    2
                )
                : 'unlimited';

            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Investment amount must be between %s and %s.',
                    number_format(
                        $fixedIncomeProduct->minimum_amount,
                        2
                    ),
                    $maximum
                ),
            ], 422);
        }

        $interest = $this->calculateInterest(
            $fixedIncomeProduct,
            $amount
        );

        $maturityAmount = $amount + $interest;

        $maturityDate = $this->calculateMaturityDate(
            $fixedIncomeProduct
        );

        return response()->json([
            'success' => true,

            'data' => [
                'product_id' => $fixedIncomeProduct->id,
                'product' => $fixedIncomeProduct->name,

                'amount' => $amount,
                'currency' => $fixedIncomeProduct->currency,

                'interest_rate' =>
                    $fixedIncomeProduct->interest_rate,

                'rate_type' =>
                    $fixedIncomeProduct->rate_type,

                'expected_interest' =>
                    round($interest, 2),

                'expected_maturity_amount' =>
                    round($maturityAmount, 2),

                'maturity_date' =>
                    $maturityDate?->format('Y-m-d'),

                'tenor_days' =>
                    $fixedIncomeProduct->tenor_days,

                'interest_frequency' =>
                    $fixedIncomeProduct->interest_frequency,
            ],
        ]);
    }

    /**
     * Validate that a user may proceed with a proposed investment.
     *
     * This still does NOT debit the wallet.
     */
    public function validateInvestment(
        Request $request,
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        $user = auth()->user();

        if ($fixedIncomeProduct->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This Fixed Income product is not currently available.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);

        $amount = (float) $validated['amount'];

        if (! $fixedIncomeProduct->acceptsAmount($amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Investment amount is outside the allowed product limits.',
            ], 422);
        }

        /*
         * Check subscription window.
         */
        $today = now()->startOfDay();

        if (
            $fixedIncomeProduct->start_date &&
            $today->lt(
                $fixedIncomeProduct->start_date->startOfDay()
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Investment subscriptions have not started for this product.',
            ], 422);
        }

        if (
            ! $fixedIncomeProduct->open_ended &&
            $fixedIncomeProduct->end_date &&
            $today->gt(
                $fixedIncomeProduct->end_date->endOfDay()
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'The investment subscription period has ended.',
            ], 422);
        }

        /*
         * Basic user/account check.
         *
         * We will connect the full KYC eligibility system
         * in the security/production sprint after confirming
         * the existing Xavier KYC implementation.
         */
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Investment is eligible to proceed.',
            'data' => [
                'product_id' => $fixedIncomeProduct->id,
                'amount' => $amount,
                'currency' => $fixedIncomeProduct->currency,
                'execution_mode' =>
                    $fixedIncomeProduct->execution_mode,
                'provider' =>
                    $fixedIncomeProduct->provider,
            ],
        ]);
    }

    /**
     * Calculate interest.
     *
     * Current implementation uses simple annual interest
     * based on tenor days.
     */
    private function calculateInterest(
        FixedIncomeProduct $product,
        float $amount
    ): float {
        if (
            $product->interest_rate === null ||
            $product->interest_rate <= 0
        ) {
            return 0;
        }

        $rate = $product->interest_rate / 100;

        $days = $product->tenor_days;

        /*
         * If no tenor is specified, we cannot safely
         * calculate a time-based return.
         */
        if (! $days) {
            return 0;
        }

        return $amount * $rate * ($days / 365);
    }

    /**
     * Calculate estimated maturity date.
     */
    private function calculateMaturityDate(
        FixedIncomeProduct $product
    ) {
        if (! $product->tenor_days) {
            return null;
        }

        return now()->addDays(
            $product->tenor_days
        );
    }
}