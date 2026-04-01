<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        // Setup the unverified user
        $user = User::factory()->unverified()->create();

        // ONLY fake the Verified event
        // Event::fake([Verified::class]);

        // Generate the URL exactly how the notification would
        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        // Act
        $response = $this->get($verificationUrl);

        // Assertions
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user, 'sanctum')->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_notification_contains_frontend_welcome_verify_url(): void
    {
        $user = User::factory()->unverified()->create();

        $notification = new VerifyEmailNotification;
        $mailMessage = $notification->toMail($user);

        $this->assertStringContainsString('/welcome?verify_url=', $mailMessage->actionUrl);

        $verifyUrl = parse_url($mailMessage->actionUrl, PHP_URL_QUERY);
        parse_str($verifyUrl, $queryParts);

        $this->assertArrayHasKey('verify_url', $queryParts);
        $this->assertStringContainsString('/api/verify-email/', urldecode($queryParts['verify_url']));
    }
}
