<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\KycProfile;
use App\Models\KycSetting;
use App\Models\ActivityLog;
use App\Services\KycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class KycWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::factory()->create();

        foreach ([
            ['tier' => 1, 'tier_name' => 'Basic', 'daily_limit' => 50000],
            ['tier' => 2, 'tier_name' => 'Mid', 'daily_limit' => 1000000],
            ['tier' => 3, 'tier_name' => 'Full', 'daily_limit' => 999999999],
        ] as $setting) {
            KycSetting::updateOrCreate(
                ['tier' => $setting['tier']],
                $setting
            );
        }
    }

    /**
     * Test user can submit KYC with BVN and NIN
     */
    public function test_user_can_submit_kyc_with_bvn_and_nin()
    {
        $response = $this->actingAs($this->user)->postJson('/api/profile/kyc', [
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'tin' => '12345678',
        ]);

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('kyc_profiles', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // User status should be updated to pending
        $this->assertEquals('pending', $this->user->fresh()->kyc_status);
    }

    /**
     * Test KYC submission requires at least BVN or NIN
     */
    public function test_kyc_submission_requires_bvn_or_nin()
    {
        $response = $this->actingAs($this->user)->postJson('/api/profile/kyc', [
            'tin' => '12345678',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseMissing('kyc_profiles', ['user_id' => $this->user->id]);
    }

    /**
     * Test KYC submission with file uploads
     */
    public function test_kyc_submission_with_file_uploads()
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for image fake uploads.');
        }

        $photo = UploadedFile::fake()->image('selfie.jpg');
        $document = UploadedFile::fake()->image('id.png');

        $response = $this->actingAs($this->user)->postJson('/api/profile/kyc', [
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'id_type' => 'national_id',
            'photo' => $photo,
            'document' => $document,
        ]);

        $response->assertSuccessful();

        $kyc = KycProfile::where('user_id', $this->user->id)->first();
        $this->assertNotNull($kyc->photo);
        $this->assertNotNull($kyc->national_id);
        
        // Verify files were stored
        Storage::disk('public')->assertExists($kyc->photo);
        Storage::disk('public')->assertExists($kyc->national_id);
    }

    /**
     * Test retrieving masked KYC data
     */
    public function test_get_kyc_returns_masked_data()
    {
        KycProfile::create([
            'user_id' => $this->user->id,
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'status' => 'verified',
            'tier' => 1,
            'level' => 'basic',
            'daily_limit' => 50000,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/profile/kyc');

        $response->assertSuccessful();
        
        $data = $response->json('data');
        
        // BVN and NIN should be masked
        $this->assertStringContainsString('*', $data['bvn']);
        $this->assertStringContainsString('*', $data['nin']);
        
        // Last 4 digits should be visible
        $this->assertStringEndsWith('8901', $data['bvn']);
        $this->assertStringEndsWith('2101', $data['nin']);
    }

    /**
     * Test KYC service masking utility
     */
    public function test_kyc_service_masks_pii_correctly()
    {
        $bvn = '12345678901';
        $masked = KycService::maskPii($bvn);

        $this->assertStringContainsString('*', $masked);
        $this->assertStringEndsWith('8901', $masked);
        $this->assertEquals('*******8901', $masked);
    }

    /**
     * Test masking null or empty values
     */
    public function test_kyc_service_handles_empty_pii()
    {
        $this->assertEquals('Not Available', KycService::maskPii(null));
        $this->assertEquals('Not Available', KycService::maskPii(''));
    }

    /**
     * Test webhook handles successful verification
     */
    public function test_webhook_handles_successful_verification()
    {
        KycProfile::create([
            'user_id' => $this->user->id,
            'bvn' => null,
            'nin' => null,
            'status' => 'pending',
        ]);

        $webhookData = [
            'status' => 'VERIFIED',
            'reference' => $this->user->id,
            'identity' => [
                'bvn' => '12345678901',
                'nin' => '98765432101',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'document' => [
                    'type' => 'national_id',
                    'number' => 'A12345678',
                ],
            ],
        ];

        $response = $this->postJson('/api/qoreid/webhook', $webhookData);

        $response->assertSuccessful();
        
        $kyc = KycProfile::where('user_id', $this->user->id)->first();
        $this->assertEquals('verified', $kyc->status);
        $this->assertEquals(1, $kyc->tier);
        $this->assertEquals('basic', $kyc->level);
    }

    /**
     * Test webhook handles failed verification
     */
    public function test_webhook_handles_failed_verification()
    {
        KycProfile::create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $webhookData = [
            'status' => 'FAILED',
            'reference' => $this->user->id,
            'reason' => 'Document not clear',
        ];

        $response = $this->postJson('/api/qoreid/webhook', $webhookData);

        $response->assertSuccessful();
        
        $kyc = KycProfile::where('user_id', $this->user->id)->first();
        $this->assertEquals('rejected', $kyc->status);
        $this->assertEquals('Document not clear', $kyc->rejection_reason);
    }

    /**
     * Test KYC profile model methods
     */
    public function test_kyc_profile_model_methods()
    {
        $kyc = KycProfile::create([
            'user_id' => $this->user->id,
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'status' => 'verified',
            'tier' => 1,
            'level' => 'basic',
            'daily_limit' => 50000,
        ]);

        // Test isVerified method
        $this->assertTrue($kyc->isVerified());

        // Test isPending method
        $kyc->status = 'pending';
        $this->assertTrue($kyc->isPending());

        // Test isRejected method
        $kyc->status = 'rejected';
        $this->assertTrue($kyc->isRejected());

        // Test masked accessors
        $kyc->status = 'verified';
        $this->assertStringEndsWith('8901', $kyc->masked_bvn);
        $this->assertStringEndsWith('2101', $kyc->masked_nin);
    }

    /**
     * Test activity logging for KYC submission
     */
    public function test_kyc_submission_logs_activity()
    {
        $this->actingAs($this->user)->postJson('/api/profile/kyc', [
            'bvn' => '12345678901',
            'nin' => '98765432101',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->user->id,
            'activity' => 'KYC Submission Started',
        ]);
    }

    /**
     * Test tier determination based on verified documents
     */
    public function test_kyc_service_determines_tier_correctly()
    {
        // Tier 1: Basic with BVN + NIN
        $tier1 = KycProfile::create([
            'user_id' => $this->user->id,
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'status' => 'verified',
        ]);
        $this->assertEquals(1, KycService::determineTier($tier1));

        // Tier 2: with international passport
        $user2 = User::factory()->create();
        $tier2 = KycProfile::create([
            'user_id' => $user2->id,
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'intl_passport' => 'passport.pdf',
            'status' => 'verified',
        ]);
        $this->assertEquals(2, KycService::determineTier($tier2));

        // Unverified: Tier 0
        $unverified = KycProfile::create([
            'user_id' => User::factory()->create()->id,
            'bvn' => '12345678901',
            'status' => 'pending',
        ]);
        $this->assertEquals(0, KycService::determineTier($unverified));
    }

    /**
     * Test formatted KYC response
     */
    public function test_kyc_formatted_response_structure()
    {
        $kyc = KycProfile::create([
            'user_id' => $this->user->id,
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'status' => 'verified',
            'tier' => 1,
            'level' => 'basic',
            'daily_limit' => 50000,
        ]);

        $formatted = KycService::formatKycResponse($kyc);

        $this->assertArrayHasKey('id', $formatted);
        $this->assertArrayHasKey('status', $formatted);
        $this->assertArrayHasKey('verified', $formatted);
        $this->assertArrayHasKey('tier', $formatted);
        $this->assertArrayHasKey('daily_limit', $formatted);
        $this->assertTrue($formatted['verified']);
        $this->assertEquals(50000, $formatted['daily_limit']);
    }

    /**
     * Test user can retrieve full profile with KYC
     */
    public function test_profile_show_includes_kyc_data()
    {
        KycProfile::create([
            'user_id' => $this->user->id,
            'bvn' => '12345678901',
            'nin' => '98765432101',
            'status' => 'verified',
            'tier' => 1,
            'level' => 'basic',
            'daily_limit' => 50000,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/profile/me');

        $response->assertSuccessful();
        $this->assertIsArray($response->json('data'));
    }
}
