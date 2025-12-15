<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\ServiceConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up a user for testing the admin endpoints.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Create a basic unverified user factory if it doesn't exist
        if (!method_exists(User::class, 'factory')) {
            // Assume the test setup ensures User model has a factory or methods for creating roles
        }
    }

    /**
     * Helper method to create an authorized user (Admin or Compliance).
     */
    protected function createAuthorizedUser(string $role = 'admin'): User
    {
        // Based on your plan, both 'admin' and 'compliance' roles should pass AdminMiddleware
        return User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
    }

    // =========================================================================
    // ACCESS CONTROL TESTS
    // =========================================================================

    public function test_unauthorized_user_cannot_access_services(): void
    {
        $user = User::factory()->create(['role' => 'client']); // Unauthorized role

        // Test the index endpoint
        $this->actingAs($user)
            ->getJson('/api/admin/services')
            ->assertStatus(403); // Forbidden

        // Test the store endpoint
        $this->actingAs($user)
            ->postJson('/api/admin/services', [])
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_services(): void
    {
        // Do not call actingAs()

        // Test the index endpoint
        $this->getJson('/api/admin/services')
            ->assertStatus(401); // Unauthorized (due to auth:sanctum)
    }

    // =========================================================================
    // SERVICE CRUD TESTS (INDEX & STORE)
    // =========================================================================

    public function test_authorized_user_can_list_services(): void
    {
        // Arrange
        $admin = $this->createAuthorizedUser('admin');
        $service = Service::factory()->create(['name' => 'Test Service', 'type' => 'test_type']);

        // Act
        $response = $this->actingAs($admin)
            ->getJson('/api/admin/services');

        // Assert
        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Test Service'])
            ->assertJsonCount(1, 'services');
    }

    public function test_authorized_user_can_create_new_service(): void
    {
        // Arrange
        $compliance = $this->createAuthorizedUser('compliance');
        $payload = [
            'name' => 'New Service ABC',
            'type' => 'abc_service',
        ];

        // Act
        $response = $this->actingAs($compliance)
            ->postJson('/api/admin/services', $payload);

        // Assert
        $response->assertStatus(201) // Created
            ->assertJsonFragment(['name' => 'New Service ABC']);

        $this->assertDatabaseHas('services', [
            'type' => 'abc_service',
            'is_active' => false, // Default should be false
        ]);
    }

    public function test_cannot_create_service_with_duplicate_type(): void
    {
        // Arrange
        $admin = $this->createAuthorizedUser('admin');
        Service::factory()->create(['type' => 'duplicate_type']);
        
        $payload = ['name' => 'Another Service', 'type' => 'duplicate_type'];

        // Act
        $response = $this->actingAs($admin)
            ->postJson('/api/admin/services', $payload);

        // Assert
        $response->assertStatus(422) // Validation Error
            ->assertJsonValidationErrors('type');
    }

    // =========================================================================
    // CONNECTION TESTS
    // =========================================================================

    public function test_can_add_new_connection_to_service(): void
    {
        // Arrange
        $admin = $this->createAuthorizedUser('admin');
        $service = Service::factory()->create();
        $payload = [
            'mode' => 'live',
            'base_url' => 'https://api.live.com',
            'credentials' => ['key' => 'live_key'],
            'headers' => ['Accept' => 'application/json'],
        ];

        // Act
        $response = $this->actingAs($admin)
            ->postJson("/api/admin/services/{$service->id}/connection", $payload);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('service_connections', [
            'service_id' => $service->id,
            'mode' => 'live',
            'base_url' => 'https://api.live.com',
            'is_active' => true,
        ]);
    }

    public function test_adding_new_connection_deactivates_old_connection_for_same_mode(): void
    {
        // Arrange
        $admin = $this->createAuthorizedUser('admin');
        $service = Service::factory()->create();

        // Create an initial active connection (Mode: live)
        $oldConnection = ServiceConnection::factory()->create([
            'service_id' => $service->id,
            'mode' => 'live',
            'base_url' => 'https://api.old.com',
            'is_active' => true,
        ]);

        // Payload for the new connection (Same mode: live)
        $newPayload = [
            'mode' => 'live',
            'base_url' => 'https://api.new.com',
        ];

        // Act
        $this->actingAs($admin)
            ->postJson("/api/admin/services/{$service->id}/connection", $newPayload)
            ->assertStatus(201);

        // Assert 1: Old connection is deactivated
        $this->assertDatabaseHas('service_connections', [
            'id' => $oldConnection->id,
            'is_active' => false,
        ]);

        // Assert 2: New connection is activated
        $this->assertDatabaseHas('service_connections', [
            'service_id' => $service->id,
            'base_url' => 'https://api.new.com',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // ACTIVATION TESTS
    // =========================================================================

    public function test_can_toggle_service_to_active(): void
    {
        // Arrange
        $admin = $this->createAuthorizedUser('admin');
        // Create another service that should be deactivated
        $otherService = Service::factory()->create(['is_active' => true]);
        // The service we want to activate
        $targetService = Service::factory()->create(['is_active' => false]);

        // Act
        $response = $this->actingAs($admin)
            ->postJson("/api/admin/services/{$targetService->id}/activate");

        // Assert
        $response->assertStatus(200);

        // Assert 1: Target service is active
        $this->assertDatabaseHas('services', [
            'id' => $targetService->id,
            'is_active' => true,
        ]);

        // Assert 2: Old active service is deactivated (Global toggle logic)
        $this->assertDatabaseHas('services', [
            'id' => $otherService->id,
            'is_active' => false,
        ]);
    }
}