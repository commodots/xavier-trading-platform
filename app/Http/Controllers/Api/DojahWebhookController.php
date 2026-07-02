<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KycProfile;
use App\Models\KycVerification;
use App\Models\User;
use App\Notifications\KycStatusNotification;
use App\Services\DojahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DojahWebhookController extends Controller
{
    public function __construct(private DojahService $dojah) {}

    /**
     * Handle Dojah webhook callbacks
     * POST /api/webhooks/dojah
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header(config('services.dojah.webhook_signature_header', 'X-Dojah-Signature'));

        // Verify webhook signature
        if (! $this->dojah->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Dojah webhook signature verification failed');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = $request->all();
        Log::info('Dojah webhook received', ['data' => $data]);

        try {
            // Dojah commonly wraps webhook sections in either 'entity' or 'data'.
            $webhookData = $data['entity'] ?? $data['data'] ?? $data;

            // Process selfie/liveness verification
            if (isset($webhookData['selfie'])) {
                $this->processSelfieVerification($webhookData['selfie']);
            }

            // Process BVN/NIN verification from government_data wrapper
            if (isset($webhookData['government_data'])) {
                $this->processGovernmentDataVerification($webhookData['government_data']);
            }

            // Process other verification sections if they exist
            if (isset($webhookData['aml'])) {
                $this->processAmlVerification($webhookData['aml']);
            }

            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            Log::error('Dojah webhook processing error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    private function processSelfieVerification(array $section): void
    {
        $status = $section['status'] ?? $section['verification_status'] ?? false;
        $message = $section['message'] ?? '';
        $data = $section['data'] ?? $section['entity'] ?? $section;

        $verificationId = $data['reference_id'] ?? $data['referenceId'] ?? $data['id'] ?? $section['reference_id'] ?? $section['referenceId'] ?? null;

        if (! $verificationId) {
            Log::warning('Dojah webhook: No verification ID found in selfie section');

            return;
        }

        $verification = KycVerification::where('verification_id', $verificationId)->first();
        if (! $verification) {
            Log::warning("Dojah webhook: Verification record not found for ID {$verificationId}");

            return;
        }

        // Idempotency: Avoid processing already approved hooks
        if ($verification->status === 'approved') {
            Log::info("Dojah webhook: Selfie verification ID {$verificationId} already processed.");

            return;
        }

        $user = User::findOrFail($verification->user_id);

        $isSuccessful = $this->isSuccessfulStatus($status);
        $verificationStatus = $isSuccessful ? 'approved' : 'failed';

        $verification->update([
            'status' => $verificationStatus,
            'response_json' => json_encode($section),
        ]);

        if ($isSuccessful) {
            DB::transaction(function () use ($user, $data, $message) {
                // Update KYC profile
                KycProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'status' => 'verified',
                        'tier' => 3,
                        'level' => 'tier3_completed',
                        'verified_at' => now(),
                    ]
                );

                // Handle camelCase/snake_case for asset delivery URL maps
                $selfieUrl = $data['selfieUrl'] ?? $data['selfie_url'] ?? null;
                if ($selfieUrl) {
                    $this->saveProfileImageFromUrl($user, $selfieUrl);
                }

                // Update core user attributes
                $user->update([
                    'verification_level' => 3,
                    'kyc_status' => 'verified',
                ]);

                ActivityLog::log($user->id, 'KYC Face Verified (Webhook)', ['message' => $message]);
            });

            $user->notify(new KycStatusNotification('verified', 3));
        } else {
            Log::warning("Dojah selfie verification failed for user {$user->id}: {$message}");
        }
    }

    private function processGovernmentDataVerification(array $section): void
    {
        $data = $section['data'] ?? [];

        // Check for BVN
        if (isset($data['bvn'])) {
            $bvnBlock = $data['bvn'];
            $bvnData = $bvnBlock['entity'] ?? $bvnBlock['data'] ?? [];
            $verificationId = $bvnData['reference_id'] ?? $bvnData['referenceId'] ?? $bvnData['id'] ?? null;

            // Check status at sub-service row
            $bvnStatus = $bvnBlock['status'] ?? $bvnBlock['verification_status'] ?? false;
            $bvnSuccess = $this->isSuccessfulStatus($bvnStatus);

            if ($verificationId) {
                $this->processBvnVerification($verificationId, $bvnSuccess, $bvnBlock);
            }
        }

        // Check for NIN
        if (isset($data['nin'])) {
            $ninBlock = $data['nin'];
            $ninData = $ninBlock['entity'] ?? $ninBlock['data'] ?? [];
            $verificationId = $ninData['reference_id'] ?? $ninData['referenceId'] ?? $ninData['id'] ?? null;

            // Check status at sub-service row
            $ninStatus = $ninBlock['status'] ?? $ninBlock['verification_status'] ?? false;
            $ninSuccess = $this->isSuccessfulStatus($ninStatus);

            if ($verificationId) {
                $this->processNinVerification($verificationId, $ninSuccess, $ninBlock);
            }
        }
    }

    private function processBvnVerification(string $verificationId, bool $status, array $section): void
    {
        $verification = KycVerification::where('verification_id', $verificationId)->first();

        if ($verification && $verification->status !== 'approved') {
            $user = User::findOrFail($verification->user_id);

            // Wrapped state changes securely inside a database transaction
            DB::transaction(function () use ($verification, $user, $status, $section) {
                $verification->update([
                    'status' => $status ? 'approved' : 'failed',
                    'response_json' => json_encode($section),
                ]);

                if ($status) {
                    ActivityLog::log($user->id, 'KYC BVN Verified (Webhook)');
                    $this->updateVerificationLevel($user);
                }
            });
        }
    }

    private function processNinVerification(string $verificationId, bool $status, array $section): void
    {
        $verification = KycVerification::where('verification_id', $verificationId)->first();

        if ($verification && $verification->status !== 'approved') {
            $user = User::findOrFail($verification->user_id);

            // Wrapped state changes securely inside a database transaction
            DB::transaction(function () use ($verification, $user, $status, $section) {
                $verification->update([
                    'status' => $status ? 'approved' : 'failed',
                    'response_json' => json_encode($section),
                ]);

                if ($status) {
                    ActivityLog::log($user->id, 'KYC NIN Verified (Webhook)');
                    $this->updateVerificationLevel($user);
                }
            });
        }
    }

    private function processAmlVerification(array $section): void
    {
        $status = $section['status'] ?? $section['verification_status'] ?? 'unknown';
        Log::info('Dojah AML verification received', ['status' => $status]);
    }

    private function isSuccessfulStatus(mixed $status): bool
    {
        return $status === true
            || $status === 'true'
            || in_array(strtolower((string) $status), ['success', 'completed', 'approved', 'verified', 'passed'], true);
    }

    private function updateVerificationLevel(User $user): void
    {
        $bvnApproved = KycVerification::where('user_id', $user->id)
            ->where('verification_type', 'bvn')
            ->whereIn('status', ['approved', 'success'])
            ->exists();

        $ninApproved = KycVerification::where('user_id', $user->id)
            ->where('verification_type', 'nin')
            ->whereIn('status', ['approved', 'success'])
            ->exists();

        if ($bvnApproved && $ninApproved) {
            $user->update(['verification_level' => 2]);
        } elseif ($user->verification_level < 1 && $user->hasVerifiedEmail()) {
            $user->update(['verification_level' => 1]);
        }
    }

    private function saveProfileImageFromUrl(User $user, string $imageUrl): void
    {
        try {
            $imageData = Http::get($imageUrl)->body();

            if (empty($imageData)) {
                Log::warning("Empty image payload retrieved from URL for user {$user->id}");

                return;
            }

            $filename = 'kyc/selfie/'.uniqid().'_'.$user->id.'.jpg';
            Storage::disk('public')->put($filename, $imageData);

            $user->update([
                'profile_image' => $filename,
                'kyc_locked' => true,
            ]);

            Log::info("Profile image updated for user {$user->id}", ['path' => $filename]);

        } catch (\Exception $e) {
            Log::error("Failed to fetch/save profile image for user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
