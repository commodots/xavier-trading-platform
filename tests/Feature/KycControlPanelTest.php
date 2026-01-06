<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\KycProfile;

class KycControlPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_kyc_settings_required_documents_and_limits()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // ensure kyc_settings exist via migrations
        $payload = [
            'settings' => [
                ['tier' => 1, 'tier_name' => 'Basic', 'daily_limit' => 50000, 'required_documents' => ['bvn','nin','national_id']],
                ['tier' => 2, 'tier_name' => 'Mid', 'daily_limit' => 200000, 'required_documents' => ['bvn','nin','intl_passport']],
            ]
        ];

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/kyc-settings', $payload)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('kyc_settings', ['tier' => 1, 'tier_name' => 'Basic']);
    }

    public function test_reviewing_kyc_moves_user_to_tier3_and_updates_user_status()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['kyc_status' => 'pending']);

        $kyc = KycProfile::create([
            'user_id' => $user->id,
            'level' => 'basic',
            'tier' => 1,
            'daily_limit' => 50000,
            'status' => 'pending'
        ]);

        $resp = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/kycs/{$kyc->id}/review", [
                'status' => 'verified',
                'tier' => 3,
                'daily_limit' => 999999999
            ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);

        $kyc->refresh();
        $user->refresh();

        $this->assertEquals(3, $kyc->tier);
        $this->assertEquals('verified', $kyc->status);
        $this->assertEquals('verified', $user->kyc_status);
    }

    public function test_upgrade_from_tier2_to_tier3()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['kyc_status' => 'pending']);

        $kyc = KycProfile::create([
            'user_id' => $user->id,
            'level' => 'mid',
            'tier' => 2,
            'daily_limit' => 200000,
            'status' => 'pending'
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/kycs/{$kyc->id}/review", [
                'status' => 'verified',
                'tier' => 3,
                'daily_limit' => 999999999
            ])->assertStatus(200);

        $kyc->refresh();
        $this->assertEquals(3, $kyc->tier);
    }

    public function test_auto_tier_assignment_based_on_required_documents()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['kyc_status' => 'pending']);

        // create kyc settings where tier 3 requires these documents
        // Ensure tiers exist or are updated instead of inserting duplicates
        \DB::table('kyc_settings')->updateOrInsert(
            ['tier' => 1],
            ['tier_name' => 'Basic', 'daily_limit' => 50000, 'required_documents' => json_encode(['bvn','nin'])]
        );
        \DB::table('kyc_settings')->updateOrInsert(
            ['tier' => 3],
            ['tier_name' => 'Full', 'daily_limit' => 999999999, 'required_documents' => json_encode(['bvn','nin','intl_passport','proof_of_address'])]
        );

        // create kyc profile with all required docs for tier 3
        $kyc = KycProfile::create([
            'user_id' => $user->id,
            'level' => 'none',
            'tier' => 1,
            'daily_limit' => 50000,
            'status' => 'pending',
            'bvn' => 'BVN123',
            'nin' => 'NIN123',
            'intl_passport' => 'path/to/passport.pdf',
            'proof_of_address' => 'path/to/poa.pdf'
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/kycs/{$kyc->id}/review", [
                'status' => 'verified',
                'daily_limit' => 999999999
            ])
            ->assertStatus(200);

        $kyc->refresh();
        $this->assertEquals(3, $kyc->tier);
    }
}
