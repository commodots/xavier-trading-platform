<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FixedIncomeProduct;
use App\Services\StaffPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FixedIncomeProductController extends Controller
{
    /**
     * Ensure the administrator/staff member has permission.
     */
    private function authorizeAccess(): void
    {
        $user = auth()->user();

        if (
            ! $user->isAdmin() &&
            ! StaffPermissionService::roleHasCapability(
                $user,
                'manage_fixed_income'
            )
        ) {
            abort(403, 'Forbidden');
        }
    }

    /**
     * List all Fixed Income products.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $query = FixedIncomeProduct::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('currency')) {
            $query->where('currency', strtoupper($request->currency));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('issuer', 'like', "%{$search}%");
            });
        }

        $products = $query
            ->orderByDesc('created_at')
            ->paginate(
                min((int) $request->get('per_page', 25), 100)
            );

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Create a Fixed Income product.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $this->validateProduct($request);

        $product = FixedIncomeProduct::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fixed Income product created successfully.',
            'data' => $product,
        ], 201);
    }

    /**
     * Display one product.
     */
    public function show(FixedIncomeProduct $fixedIncomeProduct): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json([
            'success' => true,
            'data' => $fixedIncomeProduct,
        ]);
    }

    /**
     * Update a product.
     */
    public function update(
        Request $request,
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        $this->authorizeAccess();

        /*
         * Do not allow important commercial terms to be changed once
         * investments have been made.
         */
        if ($fixedIncomeProduct->investments()->exists()) {
            $restricted = [
                'currency',
                'minimum_amount',
                'maximum_amount',
                'maximum_open_ended',
                'interest_rate',
                'rate_type',
                'interest_frequency',
                'tenor_days',
                'start_date',
                'end_date',
                'open_ended',
            ];

            foreach ($restricted as $field) {
                if (
                    $request->has($field) &&
                    $request->input($field) != $fixedIncomeProduct->{$field}
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            "The {$field} cannot be changed because investments already exist for this product.",
                    ], 422);
                }
            }
        }

        $validated = $this->validateProduct(
            $request,
            $fixedIncomeProduct
        );

        $fixedIncomeProduct->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fixed Income product updated successfully.',
            'data' => $fixedIncomeProduct->fresh(),
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        $this->authorizeAccess();

        if ($fixedIncomeProduct->investments()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This product cannot be deleted because investments exist for it. Suspend or close it instead.',
            ], 422);
        }

        $fixedIncomeProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fixed Income product deleted successfully.',
        ]);
    }

    /**
     * Activate product.
     */
    public function activate(
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        $this->authorizeAccess();

        if ($fixedIncomeProduct->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'A closed product cannot be activated.',
            ], 422);
        }

        $fixedIncomeProduct->update([
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fixed Income product activated.',
            'data' => $fixedIncomeProduct->fresh(),
        ]);
    }

    /**
     * Suspend product.
     */
    public function suspend(
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        $this->authorizeAccess();

        $fixedIncomeProduct->update([
            'status' => 'suspended',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fixed Income product suspended.',
            'data' => $fixedIncomeProduct->fresh(),
        ]);
    }

    /**
     * Close product.
     */
    public function close(
        FixedIncomeProduct $fixedIncomeProduct
    ): JsonResponse {
        $this->authorizeAccess();

        $fixedIncomeProduct->update([
            'status' => 'closed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fixed Income product closed.',
            'data' => $fixedIncomeProduct->fresh(),
        ]);
    }

    /**
     * Validation shared by create/update.
     */
    private function validateProduct(
        Request $request,
        ?FixedIncomeProduct $product = null
    ): array {
        $productId = $product?->id;

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fixed_income_products', 'code')
                    ->ignore($productId),
            ],

            'type' => [
                'required',
                'string',
                Rule::in([
                    'bond',
                    'treasury_bill',
                    'commercial_paper',
                    'sukuk',
                    'fund',
                    'other',
                ]),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'currency' => [
                'required',
                'string',
                'size:3',
            ],

            'issuer' => [
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'active',
                    'suspended',
                    'closed',
                ]),
            ],

            'minimum_amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'maximum_amount' => [
                'nullable',
                'numeric',
                'gte:minimum_amount',
            ],

            'maximum_open_ended' => [
                'boolean',
            ],

            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'open_ended' => [
                'boolean',
            ],

            'interest_rate' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'rate_type' => [
                'required',
                Rule::in([
                    'fixed',
                    'variable',
                ]),
            ],

            'interest_frequency' => [
                'required',
                Rule::in([
                    'monthly',
                    'quarterly',
                    'semi_annual',
                    'annual',
                    'at_maturity',
                ]),
            ],

            'tenor_days' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'early_withdrawal_allowed' => [
                'boolean',
            ],

            'early_withdrawal_penalty' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'subscription_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'subscription_fee_type' => [
                'nullable',
                Rule::in([
                    'none',
                    'fixed',
                    'percentage',
                ]),
            ],

            'maximum_capacity' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'execution_mode' => [
                'required',
                Rule::in([
                    'manual',
                    'automated',
                ]),
            ],

            'provider' => [
                'nullable',
                'string',
                'max:100',
            ],

            'allow_reinvestment' => [
                'boolean',
            ],

            'metadata' => [
                'nullable',
                'array',
            ],
        ]);
    }
}