<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['success', 'message', 'token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $cslProfile = $user->cslCustomerProfile()->firstOrFail();

        $this->assertSame('not_submitted', $cslProfile->status);
        $this->assertSame('0123456789', $cslProfile->bank_account_no);
        $this->assertNotSame(
            '0123456789',
            DB::table('csl_customer_profiles')->where('user_id', $user->id)->value('bank_account_no')
        );
        $this->assertSame('12345678901', $user->kyc()->firstOrFail()->bvn);
    }

    public function test_web_registration_accepts_legacy_phone_field(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Web User',
            'email' => 'web@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'phone' => '08098765432',
            'gender_cd' => 'F',
            'address_street' => '2 Example Street',
            'address_city' => 'Abuja',
            'address_country_cd' => 'NG',
            'bank_cd' => '002',
            'bank_account_name' => 'Web User',
            'bank_account_no' => '9876543210',
            'bank_branch_name' => 'Abuja',
            'bank_address_details' => '2 Example Bank Street, Abuja',
            'bank_bvn_no' => '10987654321',
        ]);

        $response->assertOk();

        $user = User::where('email', 'web@example.com')->firstOrFail();
        $this->assertSame('08098765432', $user->phone);
        $this->assertSame('not_submitted', $user->cslCustomerProfile()->firstOrFail()->status);
    }

    public function test_api_registration_accepts_legacy_phone_field(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => 'password',
            'dob' => '1990-01-01',
            'phone' => '08011112222',
            'gender_cd' => 'M',
            'address_street' => '4 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Legacy User',
            'bank_account_no' => '1234509876',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '4 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345098765',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'legacy@example.com')->firstOrFail();
        $this->assertSame('08011112222', $user->phone);
        $this->assertSame(
            '08011112222',
            $user->cslCustomerProfile()->firstOrFail()->mobile_phone_no
        );
    }

    public function test_fortify_user_creation_persists_the_csl_customer_profile(): void
    {
        $user = (new CreateNewUser)->create([
            'name' => 'Fortify User',
            'email' => 'fortify@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08098765000',
            'gender_cd' => 'F',
            'address_street' => '3 Example Street',
            'address_city' => 'Abuja',
            'address_country_cd' => 'NG',
            'bank_cd' => '003',
            'bank_account_name' => 'Fortify User',
            'bank_account_no' => '9876543211',
            'bank_branch_name' => 'Abuja',
            'bank_address_details' => '3 Example Bank Street, Abuja',
            'bank_bvn_no' => '10987654322',
        ]);

        $this->assertSame('fortify@example.com', $user->email);
        $this->assertSame('not_submitted', $user->cslCustomerProfile()->firstOrFail()->status);
    }

    public function test_fortify_user_creation_accepts_legacy_phone_field(): void
    {
        $user = (new CreateNewUser)->create([
            'name' => 'Fortify Legacy',
            'email' => 'fortify-legacy@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'phone' => '08022223333',
            'gender_cd' => 'F',
            'address_street' => '5 Example Street',
            'address_city' => 'Abuja',
            'address_country_cd' => 'NG',
            'bank_cd' => '002',
            'bank_account_name' => 'Fortify Legacy',
            'bank_account_no' => '2345610987',
            'bank_branch_name' => 'Abuja',
            'bank_address_details' => '5 Example Bank Street, Abuja',
            'bank_bvn_no' => '23456109876',
        ]);

        $this->assertSame('08022223333', $user->phone);
        $this->assertSame(
            '08022223333',
            $user->cslCustomerProfile()->firstOrFail()->mobile_phone_no
        );
    }

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'token',
            'user' => [
                'id',
                'email',
                'first_name',
                'last_name',
                'wallet',
                'kyc',
            ],
        ]);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // Verify wallet was created
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->wallet()->exists());

        // Verify KYC record was created
        $this->assertTrue($user->kyc()->exists());
    }

    public function test_registration_does_not_send_verification_email_auto(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        // Assert that no email verification notification was sent (welcome notification is OK)
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_api_verify_email_endpoint_marks_user_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($verificationUrl);

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_minimum_password_length(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_registration_prevents_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_response_includes_authentication_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_wallet_is_created_with_correct_defaults(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $wallet = $user->wallet()->first();

        $this->assertNotNull($wallet);
        $this->assertEquals('NGN', $wallet->currency);
        $this->assertEquals(0.00, $wallet->balance);
        $this->assertEquals('active', $wallet->status);
        $this->assertStringStartsWith('XAV', $wallet->account_number);
    }

    public function test_registration_with_optional_fields(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'mobile_phone_no' => '1234567890',
            'dob' => '1990-01-01',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertEquals('1234567890', $user->phone);
        $this->assertEquals('1990-01-01', $user->dob->format('Y-m-d'));
    }

    public function test_name_is_split_into_first_and_last_name(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'dob' => '1990-01-01',
            'mobile_phone_no' => '08012345678',
            'gender_cd' => 'M',
            'address_street' => '1 Example Street',
            'address_city' => 'Lagos',
            'address_country_cd' => 'NG',
            'bank_cd' => '001',
            'bank_account_name' => 'Test User',
            'bank_account_no' => '0123456789',
            'bank_branch_name' => 'Lagos',
            'bank_address_details' => '1 Example Bank Street, Lagos',
            'bank_bvn_no' => '12345678901',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertEquals('Test', $user->first_name);
        $this->assertEquals('User', $user->last_name);
    }
}
