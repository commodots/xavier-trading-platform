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
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            try {
                // Find the user if they exist to link the log, otherwise use null
                $attemptedUser = User::where('email', $request->email)->first();

                ActivityLog::create([
                    'user_id'    => $attemptedUser ? $attemptedUser->id : null,
                    'activity'   => 'Failed Login',
                    'details'    => "Failed login attempt for email: {$request->email} from " . $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
            }
            return response()->json(['success' => false, 'message' => 'Invalid credentials. Please check email or password.'], 401);
        }
        $user = Auth::user();

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

        // 1. Check for 2FA requirement
        if ($user->google2fa_enabled) {

            Auth::logout();

            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'email' => $user->email
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // 🛑 Log out of the temporary session 
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
                'details'    => "User ended session successfully.",
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
