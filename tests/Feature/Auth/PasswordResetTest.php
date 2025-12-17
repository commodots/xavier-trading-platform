<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_token_for_frontend(): void
    {
        // 1. Create a user to test with
        $user = User::factory()->create([
            'email' => 'test@frontend.com',
        ]);

        // 2. Generate the token directly without sending an email
        $token = Password::getRepository()->create($user);

        // 3. Construct the full URL needed for your Vue ResetPassword component
        $url = "/reset-password?email={$user->email}&token={$token}";

        // 4. Verify token was generated
        $this->assertNotNull($token, 'Failed to generate password reset token.');
    }


    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {

            $response = $this->get('/reset-password/' . $notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $newPassword = 'newPassword';


        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $newPassword) {


            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);


            $response->assertStatus(200)
                ->assertJsonStructure(['status']);

            $this->assertTrue(
                \Illuminate\Support\Facades\Hash::check($newPassword, $user->fresh()->password),
                'The user\'s password was not correctly reset in the database.'
            );

            return true;
        });
    }
}
