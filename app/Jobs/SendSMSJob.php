<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSMSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $message,
        public ?string $phone = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $phone = $this->phone ?? $this->user->phone;

        if (!$phone) {
            Log::warning("SendSMSJob: No phone number for user {$this->user->id}");
            return;
        }

        // TODO: Integrate with SMS provider (Twilio, Africa's Talking, etc.)
        // $this->sendViaProvider($phone, $this->message);

        Log::info("SMS to {$phone}: {$this->message}");
    }

    /**
     * Send SMS via configured provider
     */
    private function sendViaProvider(string $phone, string $message): void
    {
        // Implementation depends on chosen SMS provider
        // Example for Twilio:
        // $client = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
        // $client->messages->create($phone, [
        //     'from' => config('services.twilio.from'),
        //     'body' => $message
        // ]);
    }
}