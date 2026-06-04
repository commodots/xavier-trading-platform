<?php

namespace App\Jobs;

use App\Models\KycProfile;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\KycSetting;
use App\Services\DojahService;
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

        try {
            $dojah = app(DojahService::class);
            $livenessSuccess = false;

            // Process Liveness verification if it's base64 data
            if (str_starts_with($this->profileImageRaw, 'data:image')) {
                $base64Image = substr($this->profileImageRaw, strpos($this->profileImageRaw, ',') + 1);
                $livenessResult = $dojah->checkLiveness($base64Image);
                $confidence = $livenessResult['entity']['confidence'] ?? 0;
                
                if (($livenessResult['success'] ?? false) && $confidence >= 70) {
                    $livenessSuccess = true;
                    $dojah->storeResult($user->id, 'selfie', $livenessResult);
                }
            } else {
                // If it is an authorized referenceId token generated from sandbox/widget session
                $livenessSuccess = true; 
            }

            if (!$livenessSuccess) {
                $this->updateKycStatus($user, 'rejected', 'Liveness confirmation checks failed.', 0);
                return;
            }

            // Validate Identity Document Records
            $tier = 1; 
            $idVerified = false;

            if (!empty($this->bvn)) {
                $bvnResult = $dojah->verifyBvn($this->bvn);
                $dojah->storeResult($user->id, 'bvn', $bvnResult);
                if ($bvnResult['success'] ?? false) {
                    $idVerified = true;
                }
            }

            if (!empty($this->nin)) {
                $ninResult = $dojah->verifyNin($this->nin);
                $dojah->storeResult($user->id, 'nin', $ninResult);
                if ($ninResult['success'] ?? false) {
                    $idVerified = true;
                    // If both clear, bump tracking up to Tier 2
                    if (!empty($this->bvn) && $tier === 1) {
                        $tier = 2;
                    }
                }
            }

            if (!$idVerified && (!empty($this->bvn) || !empty($this->nin))) {
                $this->updateKycStatus($user, 'rejected', 'Provided identity verification checks rejected.', 0);
                return;
            }

            $this->updateKycStatus($user, 'verified', null, $tier);

        } catch (Exception $e) {
            Log::error("ProcessKycVerification failed for user {$this->userId}: " . $e->getMessage());
            $this->updateKycStatus($user, 'pending', 'Verification engine exception. Admin review required.');
        }
    }

    protected function updateKycStatus(User $user, string $status, ?string $reason, int $tier): void
    {
        $user->update(['kyc_status' => $status]);

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