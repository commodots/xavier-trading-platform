<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserWithRelationsResource;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\UserDevice;

class AuthController extends Controller
{
    // Register (if you want a separate register endpoint)
    public function register(Request $request)
    {
        return app(\App\Http\Controllers\Api\OnboardingController::class)->onboard($request);
    }


    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');


        if (!Auth::attempt($credentials)) {
            try {
                // Find the user if they exist to link the log, otherwise use null
                $attemptedUser = User::where('email', $request->email)->first();

                ActivityLog::create([
                    'user_id'    => $attemptedUser?->id,
                    'activity'   => 'Failed Login',
                    'details'    => 'Failed login attempt for email: ' . $request->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
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
            $user->notify(new \App\Notifications\BillingAlertNotification(1000, 'Account reactivated — platform fee will be charged shortly.'));
        }

        $user->save();

        try {
            ActivityLog::create([
                'user_id'    => $user->id,
                'activity'   => 'Login',
                'details'    => "User logged in. Device: " . $request->userAgent(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
        }

       try {
            // Check for 2FA requirement before creating an access token.
            if ($user->google2fa_enabled) {
                
                // Testing the underlying value safely via the model attribute.
                
                if (empty($user->google2fa_secret)) {
                    throw new \Illuminate\Contracts\Encryption\DecryptException("2FA secret configuration missing.");
                }

                Auth::logout();
                return response()->json([
                    'success'      => true,
                    'requires_2fa' => true,
                    'email'        => $user->email,
                ]);
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // If the model accessor fails to decrypt the APP_KEY encrypted database field, catch it cleanly
            \Log::error("2FA Decryption failed for User #{$user->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Security configuration error. Please contact support to reset 2FA.'
            ], 500);
        }

        $token = $user->createToken('auth_token|' . substr($request->userAgent() ?? '', 0, 255) . '|' . $request->ip())->plainTextToken;

        // Track device/session and detect new device logins only after successful token is issued.
        $device = UserDevice::firstOrCreate(
            [
                'user_id' => $user->id,
                'device_name' => substr($request->userAgent() ?? '', 0, 255),
                'ip_address' => $request->ip(),
            ],
            ['last_active_at' => now()]
        );

        if ($device->wasRecentlyCreated) {
            $user->notify(new \App\Notifications\NewDeviceLoginNotification($device));
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
            ]
        ]);
    }


    // Profile
    public function profile(Request $request)
    {
        $user = $request->user()->load(['wallet', 'kyc']);
        return response()->json([
            'success' => true,
            'data' => new UserWithRelationsResource($user)
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
                    'user_id'    => $user->id,
                    'activity'   => 'Logout',
                    'details'    => 'User ended session successfully.',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
            }

            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }
        }

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }
}
