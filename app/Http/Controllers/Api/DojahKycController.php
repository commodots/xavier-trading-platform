<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KycProfile;
use App\Models\KycVerification;
use App\Notifications\KycStatusNotification;
use App\Services\DojahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DojahKycController extends Controller
{
    public function __construct(private DojahService $dojah) {}

    /** POST /api/kyc/bvn */
    public function verifyBvn(Request $request): JsonResponse
    {
        $request->validate(['bvn' => 'required|digits:11']);

        $user = $request->user();

        if (KycVerification::where('user_id', $user->id)
            ->where('verification_type', 'bvn')
            ->where('status', 'approved')->exists()
        ) {
            return response()->json(['message' => 'BVN already verified.'], 422);
        }

        $result = $this->dojah->verifyBvn($request->bvn);
        $this->dojah->storeResult($user->id, 'bvn', $result);

        if (!($result['success'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'BVN verification failed.'], 422);
        }

        $this->updateKycProfileAndLevel($user, 'bvn', $request->bvn);
        ActivityLog::log($user->id, 'KYC BVN Verified', ['bvn_tail' => substr($request->bvn, -4)]);

        return response()->json([
            'message'            => 'BVN verified successfully.',
            'verification_level' => $user->fresh()->verification_level,
        ]);
    }

    /** POST /api/kyc/nin */
    public function verifyNin(Request $request): JsonResponse
    {
        $request->validate(['nin' => 'required|digits:11']);

        $user = $request->user();

        if (KycVerification::where('user_id', $user->id)
            ->where('verification_type', 'nin')
            ->where('status', 'approved')->exists()
        ) {
            return response()->json(['message' => 'NIN already verified.'], 422);
        }

        $result = $this->dojah->verifyNin($request->nin);
        $this->dojah->storeResult($user->id, 'nin', $result);

        if (!($result['success'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'NIN verification failed.'], 422);
        }

        $this->updateKycProfileAndLevel($user, 'nin', $request->nin);
        ActivityLog::log($user->id, 'KYC NIN Verified', ['nin_tail' => substr($request->nin, -4)]);

        return response()->json([
            'message'            => 'NIN verified successfully.',
            'verification_level' => $user->fresh()->verification_level,
        ]);
    }

    /** POST /api/kyc/selfie */
    public function verifySelfie(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|string|min:100']);

        $user = $request->user();

        if ($user->verification_level < 2) {
            return response()->json([
                'message' => 'Complete BVN and NIN verification before face verification.',
                'required_level' => 2,
            ], 403);
        }

        $image = $request->image;
        if (str_contains($image, 'base64,')) {
            $image = substr($image, strpos($image, 'base64,') + 7);
        }

        if (base64_decode($image, true) === false) {
            return response()->json(['message' => 'Invalid image data.'], 422);
        }

        $result     = $this->dojah->checkLiveness($image);
        $this->dojah->storeResult($user->id, 'selfie', $result);
        $confidence = $result['entity']['confidence'] ?? 0;

        if (!($result['success'] ?? false) || $confidence < 70) {
            return response()->json([
                'message'    => 'Face verification failed. Please try again in good lighting.',
                'confidence' => $confidence,
            ], 422);
        }

        DB::transaction(function () use ($user) {
            KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                ['status' => 'approved', 'verified_at' => now()]
            );
            $user->update(['verification_level' => 3, 'kyc_status' => 'approved']);
        });

        $user->notify(new KycStatusNotification('approved', 3));
        ActivityLog::log($user->id, 'KYC Face Verified', ['confidence' => $confidence]);

        return response()->json([
            'message'            => 'Face verification successful. KYC complete.',
            'verification_level' => 3,
        ]);
    }

    /** GET /api/kyc/status */
    public function status(Request $request): JsonResponse
    {
        $user  = $request->user();
        $steps = KycVerification::where('user_id', $user->id)
            ->get(['verification_type', 'status', 'updated_at'])
            ->keyBy('verification_type');

        $bvnDone    = ($steps['bvn']->status    ?? '') === 'approved';
        $ninDone    = ($steps['nin']->status    ?? '') === 'approved';
        $selfieDone = ($steps['selfie']->status ?? '') === 'approved';

        $completed = array_sum([$bvnDone, $ninDone, $selfieDone]);
        $progress  = match ($completed) {
            0 => 25,
            1 => 50,
            2 => 75,
            default => 100,
        };

        return response()->json([
            'verification_level' => $user->verification_level,
            'progress' => $progress,
            'steps' => [
                'email' => $user->hasVerifiedEmail(),
                'bvn' => $bvnDone,
                'nin' => $ninDone,
                'selfie' => $selfieDone,
            ],
            'kyc_status' => $user->kyc_status,
        ]);
    }

    private function updateKycProfileAndLevel($user, string $field, string $value): void
    {
        DB::transaction(function () use ($user, $field, $value) {
            KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                [$field => $value, 'status' => 'pending']
            );

            // Check if both BVN and NIN are now approved → level 2
            $bvnApproved = KycVerification::where('user_id', $user->id)
                ->where('verification_type', 'bvn')
                ->where('status', 'approved')
                ->exists();

            $ninApproved = KycVerification::where('user_id', $user->id)
                ->where('verification_type', 'nin')
                ->where('status', 'approved')
                ->exists();

            if ($bvnApproved && $ninApproved && $user->verification_level < 2) {
                $user->update(['verification_level' => 2]);
            } elseif ($user->verification_level < 1 && $user->hasVerifiedEmail()) {
                // Ensure at least level 1 for email-verified users
                $user->update(['verification_level' => 1]);
            }
        });
    }
}
