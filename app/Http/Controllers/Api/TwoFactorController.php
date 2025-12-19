<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use App\Models\User;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function enable2FA(Request $request)
    {
        $user = $request->user();
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        $secret = $google2fa->generateSecretKey();

        $user->google2fa_secret = $secret;
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'Xavier Trading App',
            $user->email,
            $secret
        );
$renderer = new \BaconQrCode\Renderer\Image\SvgImageBackEnd();
    $writer = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
        $renderer
    ));

    $qrImage = $writer->writeString($qrCodeUrl);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr' => $qrImage
        ]);
    }

    public function confirm2FA(Request $request)
    {
        $request->validate(['code' => 'required|numeric|digits:6']);

        $user = $request->user();

        $isValid = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);

        if (!$isValid) {
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
            'token' => 'required|numeric|digits:6'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->google2fa_enabled) {
            return response()->json(['success' => false, 'message' => 'Unauthorized or 2FA not required'], 403);
        }

        $isValid = $this->google2fa->verifyKey($user->google2fa_secret, $request->token);

        if (!$isValid) {
            return response()->json(['success' => false, 'message' => 'Invalid 2FA token'], 422);
        }

        
        $authToken = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $authToken,
            'user' => $user
        ]);
    }
}
