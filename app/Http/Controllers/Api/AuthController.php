<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserWithRelationsResource;

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
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials provided.'],
            ]);
        }

        // delete existing tokens if you want single session
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->token = $token;

        return response()->json([
		'token' => $token,
		'user' => [
			'id' => $user->id,
			'name' => $user->name,
			'email' => $user->email,
			'role' => $user->role,       // <<< REQUIRED
			'wallet' => $user->wallet ?? null,
			'kyc' => $user->kyc ?? null,
		]
	]);
    }

    // Profile (requires auth:sanctum)
    public function profile(Request $request)
    {
        $user = $request->user()->load(['wallet','kyc']);
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
