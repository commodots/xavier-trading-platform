<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\KycProfile;
use App\Models\ActivityLog;
use App\Services\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QoreidWebhookController extends Controller
{
    /**
     * Handle incoming QoreID webhook notifications
     *
     * QoreID sends webhook events when verification is complete
     * This handler validates the signature, extracts identity data, and updates KYC status
     */
    public function handle(Request $request)
    {
        $secret = config('services.qoreid.webhook_secret');
        $sigHeader = config('services.qoreid.webhook_signature_header', 'X-Qoreid-Signature');

        // Verify webhook signature for security
        if ($secret) {
            $payload = $request->getContent();
            $headerSig = $request->header($sigHeader);
            $expected = hash_hmac('sha256', $payload, $secret);

            if (!$headerSig || !hash_equals($expected, $headerSig)) {
                Log::warning('QoreID Webhook signature mismatch', [
                    'header' => $headerSig,
                    'expected' => $expected,
                    'payload_size' => strlen($payload)
                ]);
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        }

        Log::info('QoreID Webhook received', [
            'status' => $request->input('status'),
            'reference' => $request->input('reference'),
        ]);

        // Extract verification status from QoreID
        $status = strtoupper($request->input('status')); // 'VERIFIED', 'SUCCESS', or 'FAILED'
        
        // QoreID passes user reference in custom parameters
        
        $reference = $request->input('reference') 
            ?? $request->input('userData.reference') 
            ?? $request->input('customData.user_id');

        // Find user by reference (typically user ID)
        $user = User::find($reference);

        if (!$user) {
            Log::error('QoreID Webhook: User not found', [
                'reference' => $reference
            ]);
            return response()->json(['message' => 'User not found'], 404);
        }

        // Handle successful verification
        if ($status === 'VERIFIED' || $status === 'SUCCESS') {
            return $this->handleVerificationSuccess($user, $request);
        }

        // Handle failed verification
        if ($status === 'FAILED' || $status === 'REJECTED') {
            return $this->handleVerificationFailed($user, $request);
        }

        Log::warning('QoreID Webhook: Unknown status', [
            'status' => $status,
            'user_id' => $user->id
        ]);

        return response()->json(['success' => false, 'message' => 'Unknown status'], 400);
    }

    /**
     * Handle successful KYC verification
     *
     * Extract identity data from QoreID response and update KYC profile
     */
    private function handleVerificationSuccess(User $user, Request $request)
    {
        try {
            // Extract data from QoreID webhook
            $qoreidData = $request->all();
            $mappedData = KycService::extractQoreidData($qoreidData);

            // Get tier 1 settings for basic verification
            $tier1Setting = \App\Models\KycSetting::where('tier', 1)->first();
            $dailyLimit = $tier1Setting?->daily_limit ?? 500000;

            // Update or create KYC profile with verified data
            $kyc = KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'verified',
                    'level' => 'basic',
                    'tier' => 1,
                    'daily_limit' => $dailyLimit,
                    'verified_at' => now(),
                    'bvn' => $mappedData['bvn'] ?? null,
                    'nin' => $mappedData['nin'] ?? null,
                    'id_type' => $mappedData['id_type'] ?? null,
                    'id_number' => $mappedData['id_number'] ?? null,
                    'first_name' => $mappedData['first_name'] ?? null,
                    'last_name' => $mappedData['last_name'] ?? null,
                    'meta' => $mappedData['meta'] ?? [],
                ]
            );

            // Update user KYC status
            $user->update(['kyc_status' => 'verified']);

            // Log successful verification
            ActivityLog::log($user->id, 'KYC Verification Successful', [
                'method' => 'QoreID Webhook',
                'bvn_masked' => KycService::maskPii($kyc->bvn),
                'nin_masked' => KycService::maskPii($kyc->nin),
                'tier' => $kyc->tier,
                'daily_limit' => $kyc->daily_limit,
            ]);

            Log::info('QoreID Verification Success', [
                'user_id' => $user->id,
                'kyc_id' => $kyc->id,
                'tier' => $kyc->tier
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KYC verification completed successfully',
                'data' => [
                    'kyc_id' => $kyc->id,
                    'status' => $kyc->status,
                    'tier' => $kyc->tier,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing QoreID verification success', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing verification'
            ], 500);
        }
    }

    /**
     * Handle failed KYC verification
     *
     * Mark KYC as rejected and store rejection reason
     */
    private function handleVerificationFailed(User $user, Request $request)
    {
        try {
            $reason = $request->input('reason') 
                ?? $request->input('summary.biometrics.reason')
                ?? $request->input('error_message')
                ?? 'Verification failed';

            // Update KYC status to rejected
            $kyc = KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'rejected',
                    'rejection_reason' => $reason
                ]
            );

            // Update user KYC status
            $user->update(['kyc_status' => 'rejected']);

            // Log rejection
            ActivityLog::log($user->id, 'KYC Verification Failed', [
                'method' => 'QoreID Webhook',
                'reason' => $reason
            ]);

            Log::warning('QoreID Verification Failed', [
                'user_id' => $user->id,
                'reason' => $reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KYC verification rejected',
                'data' => [
                    'kyc_id' => $kyc->id,
                    'status' => $kyc->status,
                    'rejection_reason' => $kyc->rejection_reason,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing QoreID verification failure', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing rejection'
            ], 500);
        }
    }
}