<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use App\Models\RiskFlag;
use App\Services\RiskService;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(
        protected RiskService $riskService
    ) {}

    /**
     * Pending KYC verifications.
     */
    public function pending(Request $request)
    {
        $profiles = KycProfile::where('status', 'pending')
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($profiles);
    }

    /**
     * Rejected KYC verifications.
     */
    public function rejected(Request $request)
    {
        $profiles = KycProfile::where('status', 'rejected')
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($profiles);
    }

    /**
     * Verified / approved KYC verifications.
     */
    public function verified(Request $request)
    {
        $profiles = KycProfile::whereIn('status', ['verified', 'approved'])
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($profiles);
    }

    /**
     * All risk flags.
     */
    public function riskFlags(Request $request)
    {
        $query = RiskFlag::with('user');

        if ($request->severity) {
            $query->where('severity', $request->severity);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $flags = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($flags);
    }

    /**
     * Dismiss / resolve a risk flag.
     */
    public function dismissFlag(RiskFlag $flag)
    {
        $this->riskService->dismiss($flag);

        return response()->json([
            'message' => 'Risk flag dismissed',
        ]);
    }
}