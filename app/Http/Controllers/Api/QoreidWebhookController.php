<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QoreidWebhookController extends Controller
{
    /**
     * Handle incoming QoreID webhook notifications
     */
    public function handle(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json(['status' => 'webhook gateway online'], 200);
        }

        $secret = config('services.qoreid.webhook_secret');
        $sigHeader = config('services.qoreid.webhook_signature_header', 'X-Qoreid-Signature');

        // Verify webhook signature for security
        if ($secret) {
            $payload = $request->getContent();
            $headerSig = $request->header($sigHeader);
            $expected = hash_hmac('sha256', $payload, $secret);

            if (! $headerSig || ! hash_equals($expected, $headerSig)) {
                Log::warning('QoreID Webhook signature mismatch', [
                    'header' => $headerSig,
                    'expected' => $expected,
                    'payload_size' => strlen($payload),
                ]);

                return response()->json(['message' => 'Invalid signature'], 401);
            }
        }

        Log::info('QoreID Webhook received', [
            'status' => $request->input('status'),
            'reference' => $request->input('reference'),
        ]);

        // Normalize status strings to safe uppercase comparison baselines
        $status = strtoupper($request->input('status', ''));

        $payloadArray = $request->all();

        $reference = data_get($payloadArray, 'reference')
            ?? data_get($payloadArray, 'userData.reference')
            ?? data_get($payloadArray, 'customData.user_id');

        // Find user by reference tracking property identity mapping
        $user = User::find($reference);

        if (! $user) {
            Log::error('QoreID Webhook: User reference not found inside system memory', [
                'extracted_reference' => $reference,
                'raw_payload' => $payloadArray,
            ]);

            return response()->json(['message' => 'User reference not found'], 404);
        }

        // Handle successful validation pathways
        if ($status === 'VERIFIED' || $status === 'SUCCESS' || $status === 'APPROVED') {
            return $this->handleVerificationSuccess($user, $request);
        }

        // Handle negative verification thresholds
        if ($status === 'FAILED' || $status === 'REJECTED') {
            return $this->handleVerificationFailed($user, $request);
        }

        Log::warning('QoreID Webhook: Unhandled validation status state detected', [
            'status' => $status,
            'user_id' => $user->id,
        ]);

        return response()->json(['success' => false, 'message' => 'Unknown status processing loop'], 400);
    }

    /**
     * Handle successful KYC verification
     */
    private function handleVerificationSuccess(User $user, Request $request)
    {
        try {
            $qoreidData = $request->all();
            $mappedData = KycService::extractQoreidData($qoreidData);

            $tier1Setting = \App\Models\KycSetting::where('tier', 1)->first();
            $dailyLimit = $tier1Setting?->daily_limit ?? 500000;

            // Explicit array fallback protection blocks for update fields
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

            // Keep status columns in sync across tables
            $user->update(['kyc_status' => 'verified']);

            ActivityLog::log($user->id, 'KYC Verification Successful', [
                'method' => 'QoreID Webhook',
                'bvn_masked' => KycService::maskPii($kyc->bvn),
                'nin_masked' => KycService::maskPii($kyc->nin),
                'tier' => $kyc->tier,
                'daily_limit' => $kyc->daily_limit,
            ]);

            Log::info('QoreID Verification Success Integration complete', [
                'user_id' => $user->id,
                'kyc_id' => $kyc->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KYC verification completed successfully',
                'data' => [
                    'kyc_id' => $kyc->id,
                    'status' => $kyc->status,
                    'tier' => $kyc->tier,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing QoreID verification success track:', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing metrics payload data stream',
            ], 500);
        }
    }

    /**
     * Handle failed KYC verification
     */
    private function handleVerificationFailed(User $user, Request $request)
    {
        try {
            $payloadArray = $request->all();

            // Safe recursive collection fallback extraction mapping
            $reason = data_get($payloadArray, 'reason')
                ?? data_get($payloadArray, 'summary.biometrics.reason')
                ?? data_get($payloadArray, 'summary.id_status.reason')
                ?? data_get($payloadArray, 'error_message')
                ?? 'Verification data parameters failed matching criteria verification thresholds.';

            $kyc = KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                ]
            );

            $user->update(['kyc_status' => 'rejected']);

            ActivityLog::log($user->id, 'KYC Verification Failed', [
                'method' => 'QoreID Webhook',
                'reason' => $reason,
            ]);

            Log::warning('QoreID Verification Triage Rejected tracking metrics:', [
                'user_id' => $user->id,
                'reason' => $reason,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'KYC verification rejected status logs populated',
                'data' => [
                    'kyc_id' => $kyc->id,
                    'status' => $kyc->status,
                    'rejection_reason' => $kyc->rejection_reason,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing QoreID verification failure payload trace mapping:', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing rejection logging metrics backend track.',
            ], 500);
        }
    }
}
