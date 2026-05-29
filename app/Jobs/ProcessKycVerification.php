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

    public function __construct(int $userId, string $bvn, string $nin, string $imagePath, string $firstName, string $lastName)
    {
        $this->userId = $userId;
        $this->bvn = $bvn;
        $this->nin = $nin;
        $this->imagePath = $imagePath;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

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
            Log::error("ProcessKycVerification: Image file missing at {$fullImagePath} for user {$this->userId}.");
            $this->updateKycStatus($user, 'pending', 'Image file missing for verification.');
            return;
        }

        try {
            // Note: We include user_id as a reference parameter for QoreID to return in their webhook payload
            $verification = QoreidService::verify2FA(
                $this->bvn,
                $this->nin,
                $fullImagePath,
                [
                    'firstname' => $this->firstName, 
                    'lastname' => $this->lastName,
                    'reference' => (string) $user->id 
                ]
            );

            // Handle immediate fallback context if dummy_mode is activated globally
            if (config('services.qoreid.dummy_mode', false) && isset($verification['is_match']) && $verification['is_match']) {
                $this->updateKycStatus($user, 'verified', null, 1);
                Log::info("User {$user->id} KYC auto-verified via QoreID (Dummy Switch Mock).");
            } else {
                // Production: Keep status pending. We wait for QoreID's webhook to hit QoreidWebhookController
                Log::info("User {$user->id} payload dispatched out to QoreID nodes. Awaiting async webhook return hook.");
            }

        } catch (\Throwable $e) {
            Log::error("QoreID KYC verification job transaction failure for user {$user->id}: ".$e->getMessage());
            $this->updateKycStatus($user, 'pending', 'QoreID service runtime exception.');
        } finally {
            if ($disk->exists($this->imagePath)) {
                $disk->delete($this->imagePath);
            }
        }
    }

    protected function updateKycStatus(User $user, string $status, ?string $rejectionReason = null, int $tier = 0): void
    {
        $user->update(['kyc_status' => $status]);

        KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'level' => $status === 'verified' ? 'basic' : 'none',
                'tier' => $tier,
                'bvn' => $this->bvn,
                'nin' => $this->nin,
                'rejection_reason' => $rejectionReason,
            ]
        );
    }
}