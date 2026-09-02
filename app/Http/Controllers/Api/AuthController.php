<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserWithRelationsResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\BillingAlertNotification;
use App\Notifications\NewDeviceLoginNotification;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Register (if you want a separate register endpoint)
    public function register(Request $request)
    {
        return app(OnboardingController::class)->onboard($request);
    }

    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            try {
                // Find the user if they exist to link the log, otherwise use null
                $attemptedUser = User::where('email', $request->email)->first();

                ActivityLog::create([
                    'user_id' => $attemptedUser?->id,
                    'activity' => 'Failed Login',
                    'details' => 'Failed login attempt for email: '.$request->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Activity log failed for failed login', ['email' => $request->email, 'error' => $e->getMessage()]);
            }

            return response()->json(['success' => false, 'message' => 'Invalid credentials. Please check email or password.'], 401);
        }
        $user = Auth::user();

        // Update last_active_at on every successful login after 2FA validation.
        // Do not persist the session if 2FA is still required.
        $user->last_active_at = now();

        if ($user->subscription_status === 'inactive') {
            $user->subscription_status = 'active';
            $user->next_fee_due_at = now();
            $user->notify(new BillingAlertNotification(1000, 'Account reactivated — platform fee will be charged shortly.'));
        }

        $user->save();

        try {
            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Login',
                'details' => 'User logged in. Device: '.$request->userAgent(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Activity log failed for successful login', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        try {
            // Check for 2FA requirement before creating an access token.
            if ($user->google2fa_enabled) {

                // Testing the underlying value safely via the model attribute.

                if (empty($user->google2fa_secret)) {
                    throw new DecryptException('2FA secret configuration missing.');
                }

                Auth::logout();

                return response()->json([
                    'success' => true,
                    'requires_2fa' => true,
                    'email' => $user->email,
                ]);
            }
        } catch (DecryptException $e) {
            // If the model accessor fails to decrypt the APP_KEY encrypted database field, catch it cleanly
            \Log::error("2FA Decryption failed for User #{$user->id}: ".$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Security configuration error. Please contact support to reset 2FA.',
            ], 500);
        }

        $token = $user->createToken('auth_token|'.substr($request->userAgent() ?? '', 0, 255).'|'.$request->ip())->plainTextToken;

        // Track device/session and detect new device logins only after successful token is issued.
        $device = UserDevice::firstOrNew([
            'user_id' => $user->id,
            'device_name' => substr($request->userAgent() ?? '', 0, 255),
            'ip_address' => $request->ip(),
        ]);
        $device->last_active_at = now();
        $wasRecentlyCreated = ! $device->exists;
        $device->save();

        if ($wasRecentlyCreated) {
            $user->notify(new NewDeviceLoginNotification($device));
        }

        Auth::logout();

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'trading_mode' => $user->trading_mode,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
                'wallet' => $user->wallet ?? null,
                'kyc' => $user->kyc ?? null,
            ],
        ]);
    }

    // Profile
    public function profile(Request $request)
    {
        $user = $request->user()->load(['wallet', 'kyc']);

        return response()->json([
            'success' => true,
            'data' => new UserWithRelationsResource($user),
        ]);
    }

    // Logout (delete current access token)
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // 2. Log the activity BEFORE deleting the token
            try {
                ActivityLog::create([
                    'user_id' => $user->id,
                    'activity' => 'Logout',
                    'details' => 'User ended session successfully.',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Activity log failed for logout', ['user_id' => $user->id ?? null, 'error' => $e->getMessage()]);
            }

            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }
        }

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }
}
