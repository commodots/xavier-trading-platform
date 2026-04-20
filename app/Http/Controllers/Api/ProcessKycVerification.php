<?php

namespace App\Jobs;

use App\Models\KycProfile;
use App\Models\User;
use App\Services\QoreidService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessKycVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $bvn;
    protected $nin;
    protected $imagePath;
    protected $firstName;
    protected $lastName;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $bvn, string $nin, string $imagePath, string $firstName, string $lastName)
    {
        $this->userId = $userId;
        $this->bvn = $bvn;
        $this->nin = $nin;
        $this->imagePath = $imagePath;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::error("ProcessKycVerification: User {$this->userId} not found.");
            return;
        }

        $disk = Storage::disk('public');
        $fullImagePath = $disk->path($this->imagePath);

        if (!$disk->exists($this->imagePath)) {
            Log::error("ProcessKycVerification: Image file not found at {$fullImagePath} for user {$this->userId}.");
            $this->updateKycStatus($user, 'pending', 'Image file missing for verification.');
            return;
        }

        try {
            $verification = QoreidService::verify2FA(
                $this->bvn,
                $this->nin,
                $fullImagePath,
                ['firstname' => $this->firstName, 'lastname' => $this->lastName]
            );

            if ($verification['is_match']) {
                // Assuming a successful BVN+NIN+Selfie match grants Tier 1 (Basic)
                $this->updateKycStatus($user, 'verified', null, 1);
                Log::info("User {$user->id} KYC auto-verified via QoreID.");
            } else {
                $this->updateKycStatus($user, 'pending', 'QoreID biometric mismatch or verification failed.');
                Log::warning("User {$user->id} KYC verification failed via QoreID: Biometric mismatch.");
            }
        } catch (\Throwable $e) {
            Log::error("QoreID KYC verification failed for user {$user->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->updateKycStatus($user, 'pending', 'QoreID verification service error.');
        } finally {
            // Clean up the uploaded image after processing
            if ($disk->exists($this->imagePath)) {
                $disk->delete($this->imagePath);
            }
        }
    }

    /**
     * Update the user's KYC status and profile.
     */
    protected function updateKycStatus(User $user, string $status, ?string $rejectionReason = null, int $tier = 0): void
    {
        $user->update(['kyc_status' => $status]);

        KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'level' => $status === 'verified' ? 'basic' : 'none', // Default to basic for verified
                'tier' => $tier,
                'bvn' => $this->bvn,
                'nin' => $this->nin,
                'rejection_reason' => $rejectionReason,
            ]
        );
    }
}
