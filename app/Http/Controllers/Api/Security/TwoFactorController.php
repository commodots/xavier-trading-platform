<?php

namespace App\Http\Controllers\Api\Security;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
    ) {
    }

    /**
     * Generate 2FA setup
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is already enabled on this account.',
            ], 422);
        }

        try {
            $setup = $this->twoFactorService->enableTwoFactor($user);

            AuditService::logSecurityEvent($user, '2fa_setup_initiated', 'User initiated 2FA setup');

            return response()->json([
                'secret' => $setup['secret'],
                'recovery_codes' => $setup['recovery_codes'],
                'qr_code_url' => $setup['qr_code_url'],
                'message' => 'Scan the QR code with your authenticator app. Save your recovery codes in a safe place.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Verify 2FA token during setup
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is already enabled.',
            ], 422);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Setup 2FA first.',
            ], 422);
        }

        try {
            $google2fa = new Google2FA();
            $isValid = $google2fa->verifyKey($user->two_factor_secret, $request->token);

            if (!$isValid) {
                AuditService::logSecurityEvent($user, '2fa_verify_failed', 'Invalid TOTP token during 2FA setup');
                return response()->json(['message' => 'Invalid authentication code. Please try again.'], 422);
            }

            $this->twoFactorService->confirmTwoFactor($user);

            // Sync google2fa_enabled so login flow also recognises 2FA as active
            $user->update([
                'google2fa_enabled' => true,
                'google2fa_secret'  => $user->two_factor_secret,
            ]);

            AuditService::logSecurityEvent($user, '2fa_enabled', 'User successfully enabled 2FA');

            return response()->json([
                'message' => '2FA has been successfully enabled.',
                'recovery_codes' => $user->two_factor_recovery_codes,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // Verify password
        if (!\Hash::check($request->password, $user->password)) {
            AuditService::logSecurityEvent($user, '2fa_disable_failed', 'Invalid password provided', ['reason' => 'invalid_password']);

            return response()->json([
                'message' => 'Invalid password.',
            ], 422);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is not enabled.',
            ], 422);
        }

        try {
            $this->twoFactorService->disableTwoFactor($user);

            // Keep both flag sets in sync
            $user->update(['google2fa_enabled' => false, 'google2fa_secret' => null]);

            AuditService::logSecurityEvent($user, '2fa_disabled', 'User disabled 2FA');

            return response()->json([
                'message' => '2FA has been disabled.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get 2FA status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->two_factor_enabled || $user->google2fa_enabled,
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }
}
