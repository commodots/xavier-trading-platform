<?php

namespace App\Jobs;

use App\Models\CslCustomerProfile;
use App\Services\CSL\CslCustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateCslIndividualCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $userId) {}

    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(CslCustomerService $service): void
    {
        $service->submitForUser($this->userId);
    }

    public function failed(Throwable $exception): void
    {
        CslCustomerProfile::query()
            ->where('user_id', $this->userId)
            ->whereNull('customer_id')
            ->whereNotIn('status', ['pending_approval', 'rejected'])
            ->update([
                'status' => 'failed',
                'last_error' => 'CSL customer provisioning failed after retries.',
            ]);

        Log::error('CSL individual customer provisioning failed.', [
            'user_id' => $this->userId,
            'exception' => $exception::class,
        ]);
    }
}
