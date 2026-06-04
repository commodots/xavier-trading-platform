<?php

namespace App\Http\Controllers\Api\Security;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\AuditService;
use App\Services\RateLimitService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalService $withdrawalService,
    ) {
    }

    /**
     * List withdrawals for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $withdrawals = WithdrawalRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($withdrawals);
    }

    /**
     * Initiate a new withdrawal
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:NGN,USD',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
            'bank_code' => 'nullable|string',
        ]);

        $user = $request->user();

        // Enforce 2FA before any withdrawal
        if (!$user->google2fa_enabled && !$user->two_factor_enabled) {
            throw ValidationException::withMessages([
                '2fa' => 'You must enable Two-Factor Authentication before withdrawing.',
            ]);
        }

        // Enforce KYC level 3 (face verified) for withdrawals
        if ($user->verification_level < 3) {
            return response()->json([
                'message'            => 'Face verification required to withdraw. Please complete KYC.',
                'verification_level' => $user->verification_level,
                'required_level'     => 3,
                'action'             => 'complete_kyc',
            ], 403);
        }

        // Check rate limit
        if (RateLimitService::isWithdrawalLimited($user->id)) {
            return response()->json([
                'message' => 'Too many withdrawal requests. Please try again later.',
            ], 429);
        }

        try {
            // Check for fraud patterns
            $fraudFlags = $this->withdrawalService->checkFraudPatterns(
                $user,
                $request->amount,
                $request->currency
            );

            if (count($fraudFlags) > 0) {
                AuditService::logSecurityEvent(
                    $user,
                    'withdrawal_fraud_detected',
                    'Potential fraud detected in withdrawal attempt',
                    ['flags' => $fraudFlags]
                );

                return response()->json([
                    'message' => 'Your withdrawal requires additional verification. Contact support.',
                    'flags' => $fraudFlags,
                ], 422);
            }

            $withdrawal = $this->withdrawalService->initiateWithdrawal(
                $user,
                $request->amount,
                $request->currency,
                $request->account_number,
                $request->account_name,
                $request->bank_code
            );

            RateLimitService::recordWithdrawalAttempt($user->id);

            return response()->json([
                'withdrawal' => $withdrawal,
                'message' => 'Withdrawal request submitted successfully.',
            ], 201);
        } catch (\Exception $e) {
            AuditService::logSecurityEvent(
                $user,
                'withdrawal_failed',
                'Withdrawal initiation failed: ' . $e->getMessage()
            );

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get withdrawal details
     */
    public function show(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        if ($withdrawal->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($withdrawal);
    }

    /**
     * Admin: Approve withdrawal
     */
    public function approve(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending withdrawals can be approved.',
            ], 422);
        }

        try {
            $this->withdrawalService->approveWithdrawal($withdrawal, $request->user());

            return response()->json([
                'withdrawal' => $withdrawal->fresh(),
                'message' => 'Withdrawal approved.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Admin: Reject withdrawal
     */
    public function reject(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:10']);

        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending withdrawals can be rejected.',
            ], 422);
        }

        try {
            $this->withdrawalService->rejectWithdrawal($withdrawal, $request->user(), $request->reason);

            return response()->json([
                'withdrawal' => $withdrawal->fresh(),
                'message' => 'Withdrawal rejected.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
