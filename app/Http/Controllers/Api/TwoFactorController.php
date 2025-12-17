<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // 💡 STEP 2: Enable 2FA Setup (Authenticated)
    public function enable2FA(Request $request)
    {
        // Requires 'auth:sanctum' middleware
        $user = $request->user();

        $secret = $this->google2fa->generateSecretKey();

        $qrImage = $this->google2fa->getQRCodeInline(
            'Xavier Trading App',
            $user->email,
            $secret
        );

        // Temporarily save the secret (auto-encrypted by User model)
        $user->google2fa_secret = $secret;
        $user->save();

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr' => $qrImage
        ]);
    }

    // 💡 STEP 3: Confirm & Activate 2FA (Authenticated)
    public function confirm2FA(Request $request)
    {
        $request->validate(['code' => 'required|numeric|digits:6']);

        $user = $request->user();

        $isValid = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);

        if (!$isValid) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 422);
        }

        $user->is_2fa_enabled = true;
        $user->save();

        return response()->json(['success' => true, 'message' => '2FA Activated']);
    }

    // 💡 STEP 5: 2FA Code Verification After Login Attempt (Public)
    public function verify2FA(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|numeric|digits:6' // 'token' is the 6-digit TOTP code
        ]);

        $user = User::where('email', $request->email)->first(); // Find user by email

        if (!$user || !$user->is_2fa_enabled) {
            return response()->json(['success' => false, 'message' => 'Unauthorized or 2FA not required'], 403);
        }

        $isValid = $this->google2fa->verifyKey($user->google2fa_secret, $request->token);

        if (!$isValid) {
            return response()->json(['success' => false, 'message' => 'Invalid 2FA token'], 422);
        }

        // Issue final login token (Sanctum)
        $authToken = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $authToken,
            'user' => $user
        ]);
    }
}
