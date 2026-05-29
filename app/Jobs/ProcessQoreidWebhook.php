<?php

namespace App\Jobs;

use App\Models\KycProfile;
use App\Models\User;
use App\Notifications\KycStatusNotification;
use App\Services\KycService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessQoreidWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;

    public int $tries = 5;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(): void
    {
        $status = strtoupper($this->payload['status'] ?? '');
        $reference = $this->payload['reference']
            ?? data_get($this->payload, 'userData.reference')
            ?? data_get($this->payload, 'customData.user_id');

        $user = User::find($reference);

        if (!$user) {
            Log::error('ProcessQoreidWebhook: User not found', ['reference' => $reference]);
            return;
        }

        if ($status === 'VERIFIED' || $status === 'SUCCESS') {
            $this->processVerified($user);
            return;
        }

        if ($status === 'FAILED' || $status === 'REJECTED') {
            $this->processRejected($user);
            return;
        }

        Log::warning('ProcessQoreidWebhook: Unknown status', ['status' => $status, 'user_id' => $user->id]);
    }

    protected function processVerified(User $user): void
    {
        $mappedData = KycService::extractQoreidData($this->payload);
        $targetTier = KycService::determineTier(new KycProfile($mappedData));
        $tierSetting = KycService::getKycSetting($targetTier);

        $kyc = KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'verified',
                'level' => match ($targetTier) {
                    2 => 'mid',
                    3 => 'full',
                    default => 'basic'
                },
                'tier' => $targetTier,
                'daily_limit' => $tierSetting?->daily_limit ?? 500000,
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

        $user->update(['kyc_status' => 'verified']);
        $user->notify(new KycStatusNotification('verified', $kyc->tier));

        Log::info('ProcessQoreidWebhook: KYC verified', ['user_id' => $user->id, 'tier' => $kyc->tier]);
    }

    protected function processRejected(User $user): void
    {
        $reason = $this->payload['reason']
            ?? data_get($this->payload, 'summary.biometrics.reason')
            ?? data_get($this->payload, 'error_message')
            ?? 'Verification failed';

        $kyc = KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]
        );

        $user->update(['kyc_status' => 'rejected']);
        $user->notify(new KycStatusNotification('rejected', null, $reason));

        Log::warning('ProcessQoreidWebhook: KYC rejected', ['user_id' => $user->id, 'reason' => $reason]);
    }
}
