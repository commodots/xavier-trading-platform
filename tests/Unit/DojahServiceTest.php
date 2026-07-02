<?php

namespace Tests\Unit;

use App\Services\DojahService;
use App\Services\Kyc\Providers\DojahProvider;
use Tests\TestCase;

class DojahServiceTest extends TestCase
{
    public function test_webhook_signature_validation_accepts_sha256_prefix(): void
    {
        config()->set('services.dojah.webhook_secret', 'test-secret');

        $service = new DojahService(new DojahProvider);
        $payload = '{"event":"verification.completed"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test-secret');

        $this->assertTrue($service->verifyWebhookSignature($payload, $signature));
    }

    public function test_webhook_signature_validation_rejects_invalid_signature(): void
    {
        config()->set('services.dojah.webhook_secret', 'test-secret');

        $service = new DojahService(new DojahProvider);
        $payload = '{"event":"verification.completed"}';

        $this->assertFalse($service->verifyWebhookSignature($payload, 'sha256=invalid'));
    }
}
