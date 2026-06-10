<?php

namespace App\Services;

use App\Models\User;
use Exception;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
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
       
        $codes = $user->two_factor_recovery_codes ? json_decode($user->two_factor_recovery_codes, true) : [];

        if (($key = array_search($code, $codes, true)) !== false) {
            unset($codes[$key]);
            
            $user->two_factor_recovery_codes = json_encode(array_values($codes));
            $user->save();
            return true;
        }

        return false;
    }

    /**
     * Enable 2FA for user (returns secret and QR code URL)
     */
    /**
     * Enable 2FA for user (stores secret and codes together)
     */
    public function enableTwoFactor(User $user): array
    {
        try {
            $google2fa = new Google2FA();
            $secret = $google2fa->generateSecretKey();
            $codes = $this->generateRecoveryCodes();

            
            $payload = [
                'secret' => $secret,
                'recovery_codes' => $codes
            ];

         
            $user->google2fa_secret = json_encode($payload);
            $user->save();

            return [
                'secret' => $secret,
                'recovery_codes' => $codes,
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
            'google2fa_enabled' => false,
            'google2fa_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    /**
     * Confirm 2FA setup
     */
    public function confirmTwoFactor(User $user): void
    {
        $user->update([
            'google2fa_enabled' => true,
        ]);
    }

    /**
     * Generate QR code URL for Google Authenticator
     */
    private function generateQrCodeUrl(User $user, string $secret): string
    {
        $appName = config('app.name', 'Xavier Trading');
        $email = $user->email;

        return 'otpauth://totp/' . urlencode("{$appName}:{$email}") . '?secret=' . $secret . '&issuer=' . urlencode($appName);
    }
}
