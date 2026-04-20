<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Qoreid2FATest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test onboarding with successful BVN + NIN + Selfie match.
     */
    public function test_onboarding_with_qoreid_2fa_success(): void
    {
        $this->withoutExceptionHandling();

        Storage::fake('public');

        // Mock QoreID Auth and Complex Verification APIs
        Http::fake([
            '*/token' => Http::response(['accessToken' => 'fake_token'], 200),
            '*/complex-verification' => Http::response([
                'summary' => [
                    'biometrics' => ['match' => true]
                ],
                'status' => 'VERIFIED'
            ], 200),
        ]);

        $payload = [
            'name' => 'Aisha Ogunleye',
            'email' => 'aisha@example.test',
            'password' => 'SecurePass2025!',
            'phone' => '+2348089001122',
            'dob' => '1994-07-15',
            'bvn' => '12345678901',
            'nin' => '12345678901',
            'profile_image' => UploadedFile::fake()->create('selfie.jpg', 100),
        ];

        $response = $this->postJson('/api/onboard', $payload);

        $response->assertStatus(201);

        $user = User::where('email', 'aisha@example.test')->first();

        $this->assertEquals('verified', $user->kyc_status, 'User should be marked as verified');
        $this->assertNotNull($user->profile_image);
        $this->assertEquals('verified', $user->kyc->status);
        $this->assertEquals('12345678901', $user->kyc->bvn);
    }

    /**
     * Test onboarding when 2FA biometric match fails.
     */
    public function test_onboarding_with_qoreid_2fa_mismatch(): void
    {
        config(['services.qoreid.dummy_mode' => false]);
        
        Storage::fake('public');

        Http::fake([
            '*/token' => Http::response(['accessToken' => 'fake_token'], 200),
            '*/complex-verification' => Http::response([
                'summary' => [
                    'biometrics' => ['match' => false]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/onboard', [
            'name' => 'Test Mismatch',
            'email' => 'mismatch@example.test',
            'password' => 'SecurePass2025!',
            'bvn' => '11111111111',
            'nin' => '22222222222',
            'profile_image' => UploadedFile::fake()->create('fake.jpg', 100),
        ]);

        $response->assertStatus(201); // Account still created for user retention

        $user = User::where('email', 'mismatch@example.test')->first();

        $this->assertNotEquals('verified', $user->kyc_status);

        $this->assertEquals('pending', $user->kyc_status, 'User status should be pending on mismatch');

        $this->assertEquals('pending', $user->kyc->status, 'KYC Profile should be pending');
    }
}
