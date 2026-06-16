<?php

namespace App\Jobs;

use App\Models\KycProfile;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\KycSetting;
use App\Services\Kyc\KycProviderFactory;
use App\Services\Kyc\Contracts\KycProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessKycVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $bvn;
    protected $nin;
    protected $profileImageRaw;
    protected $firstName;
    protected $lastName;

    public function __construct(int $userId, string $bvn, string $nin, string $profileImageRaw, string $firstName, string $lastName)
    {
        $this->userId = $userId;
        $this->bvn = $bvn;
        $this->nin = $nin;
        $this->profileImageRaw = $profileImageRaw;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        // Fetch existing profile to preserve already verified data
        $existingProfile = KycProfile::where('user_id', $this->userId)->first();
        $currentTier = $existingProfile?->tier ?? 0;
        $idVerified = ($currentTier > 0);

        try {
            $provider = KycProviderFactory::make();
            $livenessSuccess = false;

            // Process Liveness verification if it's base64 data
            if (!empty($this->profileImageRaw) && str_starts_with($this->profileImageRaw, 'data:image')) {
                $base64Image = substr($this->profileImageRaw, strpos($this->profileImageRaw, ',') + 1);
                $faceResult = $provider->verifyFace([
                    'image' => $base64Image,
                    'bvn' => $this->bvn,
                    'nin' => $this->nin,
                ]);
                $confidence = $faceResult['entity']['confidence'] ?? 0;
                
                if (($faceResult['success'] ?? false) && $confidence >= 70) {
                    $livenessSuccess = true;
                }
            } elseif (!empty($this->profileImageRaw)) {
                // If it is an authorized referenceId token generated from sandbox/widget session
                $livenessSuccess = true; 
            } else {
                // No image provided at all
                $livenessSuccess = false;
            }

            if (!$livenessSuccess) {
                $this->updateKycStatus($user, 'rejected', 'Liveness confirmation checks failed.', 0);
                return;
            }

            // Validate Identity Document Records
            $tier = $currentTier ?: 1; 

            // Only verify if new data is provided, otherwise trust existing state
            if (!empty($this->bvn)) {
                $bvnResult = $provider->verifyBvn($this->bvn);
                if ($bvnResult['success'] ?? false) {
                    $idVerified = true;
                }
            }

            if (!empty($this->nin)) {
                $ninResult = $provider->verifyNin($this->nin);
                if ($ninResult['success'] ?? false) {
                    $idVerified = true;
                    $hasBvn = !empty($this->bvn) || !empty($existingProfile?->bvn);
                    if ($hasBvn && $tier === 1) {
                        $tier = 2;
                    }
                }
            }

            // If they tried to verify something but everything failed
            if (!$idVerified && (!empty($this->bvn) || !empty($this->nin))) {
                $this->updateKycStatus($user, 'rejected', 'Provided identity verification checks rejected.', 0);
                return;
            }

            // If no data was provided to evaluate, drop to none
            if (!$idVerified && empty($this->bvn) && empty($this->nin)) {
                $this->updateKycStatus($user, 'pending', 'No identity documents provided.', 0);
                return;
            }

            $this->updateKycStatus($user, 'verified', null, max($tier, $currentTier));

        } catch (Exception $e) {
            Log::error("ProcessKycVerification failed for user {$this->userId}: " . $e->getMessage());
            $this->updateKycStatus($user, 'pending', 'Verification failed. Admin review required.', $currentTier);
        }
    }

    // Swapped parameter positions so $reason can be safely null or omitted without shifting $tier
    protected function updateKycStatus(User $user, string $status, ?string $reason = null, int $tier = 0): void
    {
        $user->update([
            'kyc_status' => $status,
            'verification_level' => $tier
        ]);

        $level = match($tier) {
            1 => 'basic',
            2 => 'standard',
            3 => 'full',
            default => 'none'
        };

        $tierSettings = KycSetting::where('tier', $tier)->first();

        KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'level' => $level,
                'tier' => $tier,
                'bvn' => $this->bvn ?: null,
                'nin' => $this->nin ?: null,
                'rejection_reason' => $reason,
                'daily_limit' => $tierSettings?->daily_limit ?? 500000,
            ]
        );

        ActivityLog::log($user->id, 'KYC Engine Evaluation Complete', [
            'message' => "KYC evaluation completed: Status set to {$status}, Level assigned: {$level}."
        ]);
    }
}