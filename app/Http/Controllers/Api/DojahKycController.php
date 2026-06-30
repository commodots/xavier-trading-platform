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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        
        $inputKey = $request->has('profile_image') ? 'profile_image' : 'image';
        
        $request->validate([
            $inputKey => 'required|string|min:10'
        ]);

        $user = $request->user();

        if ($user->verification_level < 2) {
            return response()->json([
                'message' => 'Complete BVN and NIN verification before face verification.',
                'required_level' => 2,
            ], 403);
        }

        $image = $request->input($inputKey);
        
        // Store original image for profile picture
        $originalImage = $image;
        
        // If it's a Dojah reference token instead of base64, skip string transformations
        if (str_contains($image, 'base64,')) {
            $image = substr($image, strpos($image, 'base64,') + 7);
            if (base64_decode($image, true) === false) {
                return response()->json(['message' => 'Invalid image data.'], 422);
            }
        }

        // Use test mode or local environment bypass
        if (app()->environment('local') || config('services.dojah.test_mode')) {
            $result = [
                'success' => true, 
                'entity' => [
                    'confidence' => 95,
                    'image' => $originalImage // Store the image in test mode
                ]
            ];
        } else {
            $result = $this->dojah->checkLiveness($image);
        }

        $this->dojah->storeResult($user->id, 'selfie', $result);
        $confidence = $result['entity']['confidence'] ?? 0;

        if (!($result['success'] ?? false) || $confidence < 70) {
            return response()->json([
                'message'    => 'Face verification failed. Please try again in good lighting.',
                'confidence' => $confidence,
            ], 422);
        }

        DB::transaction(function () use ($user, $result) {
            // Update or initialize the corresponding model tier tracker row
            KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'verified', 
                    'tier' => 3,
                    'level' => 'tier3_completed',
                    'verified_at' => now()
                ]
            );

            // Save selfie image as profile image
            $selfieImage = $this->dojah->extractSelfieImage($result);
            
            if ($selfieImage) {
                $this->saveProfileImage($user, $selfieImage);
            }

            $user->update([
                'verification_level' => 3, 
                'kyc_status' => 'verified' 
            ]);
        });

        
        $user->notify(new KycStatusNotification('verified', 3));
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
            ->get(['verification_type', 'status'])
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

            $bvnApproved = KycVerification::where('user_id', $user->id)
                ->where('verification_type', 'bvn')
                ->where(function($query) {
                    $query->where('status', 'approved')->orWhere('status', 'success');
                })->exists();

            $ninApproved = KycVerification::where('user_id', $user->id)
                ->where('verification_type', 'nin')
                ->where(function($query) {
                    $query->where('status', 'approved')->orWhere('status', 'success');
                })->exists();

            if ($bvnApproved && $ninApproved) {
                $user->update(['verification_level' => 2]);
            } elseif ($user->verification_level < 1 && $user->hasVerifiedEmail()) {
                // Ensure at least level 1 for email-verified users
                $user->update(['verification_level' => 1]);
            }
        });
    }

    private function saveProfileImage(User $user, string $base64Image): void
    {
        try {
            // Remove data URL prefix if present
            if (str_contains($base64Image, 'base64,')) {
                $base64Image = substr($base64Image, strpos($base64Image, 'base64,') + 7);
            }

            // Decode base64 image
            $imageData = base64_decode($base64Image, true);
            
            if ($imageData === false) {
                Log::warning("Invalid base64 image for user {$user->id}");
                return;
            }

            // Generate unique filename
            $filename = 'kyc/selfie/' . uniqid() . '_' . $user->id . '.jpg';
            
            // Store the image
            Storage::disk('public')->put($filename, $imageData);

            // Update user profile image and lock it
            $user->update([
                'profile_image' => $filename,
                'kyc_locked' => true
            ]);

            Log::info("Profile image saved for user {$user->id}", ['path' => $filename]);

        } catch (\Exception $e) {
            Log::error("Failed to save profile image for user {$user->id}", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
