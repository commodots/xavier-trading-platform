<?php

namespace App\Services;

use App\Models\TwoFactorToken;
use App\Models\User;
use Exception;

class TwoFactorService
{
    /**
     * Generate a 6-digit OTP token
     */
    public function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a 2FA token for a user
     */
    public function createToken(User $user, string $type = 'login', int $minutesValid = 10): string
    {
        $token = $this->generateOtp();

        TwoFactorToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'type' => $type,
            'expires_at' => now()->addMinutes($minutesValid),
        ]);

        return $token;
    }

    /**
     * Verify a 2FA token
     */
    public function verifyToken(User $user, string $token, string $type = 'login'): bool
    {
        $record = TwoFactorToken::where('user_id', $user->id)
            ->where('token', $token)
            ->where('type', $type)
            ->active()
            ->first();

        if (!$record) {
            return false;
        }

        $record->markAsUsed();
        return true;
    }

    /**
     * Generate recovery codes for 2FA backup
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $codes = $user->two_factor_recovery_codes;

        if (($key = array_search($code, $codes)) !== false) {
            unset($codes[$key]);
            $user->update(['two_factor_recovery_codes' => array_values($codes)]);
            return true;
        }

        return false;
    }

    /**
     * Enable 2FA for user (returns secret and QR code URL)
     */
    public function enableTwoFactor(User $user): array
    {
        try {
            // Generate a random secret
            $secret = $this->generateSecret();

            // In a real implementation, you'd use a package like pragmarx/google2fa-laravel
            // For now, we'll store the secret and return it
            $user->update([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            ]);

            return [
                'secret' => $secret,
                'recovery_codes' => $user->two_factor_recovery_codes,
                'qr_code_url' => $this->generateQrCodeUrl($user, $secret),
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to enable 2FA: ' . $e->getMessage());
        }
    }

    /**
     * Disable 2FA for user
     */
    public function disableTwoFactor(User $user): void
    {
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Confirm 2FA setup
     */
    public function confirmTwoFactor(User $user): void
    {
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Generate a random secret for Google Authenticator
     */
    private function generateSecret(): string
    {
        // Generate a random 32-character base32 string
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $characters[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Generate QR code URL for Google Authenticator
     */
    private function generateQrCodeUrl(User $user, string $secret): string
    {
        $appName = config('app.name', 'Xavier Trading');
        $email = $user->email;

        // Format: otpauth://totp/AppName:email?secret=SECRET&issuer=AppName
        return 'otpauth://totp/' . urlencode("$appName:$email") . '?secret=' . $secret . '&issuer=' . urlencode($appName);
    }
}
