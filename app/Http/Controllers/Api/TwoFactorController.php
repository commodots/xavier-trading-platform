<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public function enable2FA(Request $request)
    {
        $user = $request->user();

        $secret = $this->google2fa->generateSecretKey();

        $user->google2fa_secret = $secret;
        $user->save();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            'Xavier Trading App',
            $user->email,
            $secret
        );
        $renderer = new \BaconQrCode\Renderer\Image\SvgImageBackEnd;
        $writer = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
            $renderer
        ));

        $qrImage = $writer->writeString($qrCodeUrl);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr' => $qrImage,
        ]);
    }

    public function confirm2FA(Request $request)
    {
        $request->validate(['code' => 'required|numeric|digits:6']);

        $user = $request->user();

        $isValid = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);

        if (! $isValid) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 422);
        }

        $user->google2fa_enabled = true;
        $user->save();

        return response()->json(['success' => true, 'message' => '2FA Activated']);
    }

    public function verify2FA(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|numeric|digits:6',
        ]);

        // Rate limiting on failed attempts
        $cacheKey = '2fa_attempts_'.$request->email;
        $attempts = cache()->get($cacheKey, 0);
        if ($attempts >= 5) {
            return response()->json(['success' => false, 'message' => 'Too many failed attempts. Please try again later.'], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->google2fa_enabled) {
            return response()->json(['success' => false, 'message' => 'Unauthorized or 2FA not required'], 403);
        }

        $isValid = $this->google2fa->verifyKey($user->google2fa_secret, $request->token);

        if (! $isValid) {
            cache()->put($cacheKey, $attempts + 1, 300); // Lock for 5 minutes

            return response()->json(['success' => false, 'message' => 'Invalid 2FA token'], 422);
        }

        cache()->forget($cacheKey); // Clear attempts on success

        $authToken = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $authToken,
            'user' => $user,
        ]);
    }

    public function disable2FA(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $user->google2fa_enabled = false;
        $user->google2fa_secret = null;
        $user->save();

        return response()->json(['success' => true, 'message' => '2FA has been disabled.']);
    }
}
