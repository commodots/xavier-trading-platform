<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserWithRelationsResource;
use Illuminate\Support\Facades\Auth;

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
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        $user = Auth::user();
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
                    'role' => $user->role, // <<< REQUIRED
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
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }
}

