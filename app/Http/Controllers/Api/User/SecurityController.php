<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class SecurityController extends Controller
{
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        ActivityLog::log($user->id, 'Password Changed', ['ip' => $request->ip()]);

        $currentTokenId = $this->resolveCurrentTokenId($request);

        if ($currentTokenId !== null) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }

    public function getActiveSessions(Request $request)
    {
        $currentTokenId = $this->resolveCurrentTokenId($request);

        $tokens = $request->user()->tokens()->select('id', 'name', 'last_used_at', 'created_at')->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'device'       => $t->name,
                'last_active'  => $t->last_used_at?->diffForHumans() ?? 'Never',
                'created_at'   => $t->created_at->toDateTimeString(),
                'is_current'   => $t->id === $currentTokenId,
            ]);

        return response()->json(['success' => true, 'sessions' => $tokens]);
    }

    public function logoutOtherDevices(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $this->resolveCurrentTokenId($request);

        if ($currentTokenId !== null) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        ActivityLog::log($user->id, 'Logged Out Other Devices', ['ip' => $request->ip()]);

        return response()->json(['success' => true, 'message' => 'All other sessions have been terminated.']);
    }

    private function resolveCurrentTokenId(Request $request): ?int
    {
        $currentAccessToken = $request->user()->currentAccessToken();

        if ($currentAccessToken instanceof PersonalAccessToken) {
            return $currentAccessToken->id;
        }

        if ($token = $request->bearerToken()) {
            return PersonalAccessToken::findToken($token)?->id;
        }

        return null;
    }

    public function enable2FA(Request $request)
    {
        return app(\App\Http\Controllers\Api\TwoFactorController::class)->enable2FA($request);
    }

    public function verify2FA(Request $request)
    {
        return app(\App\Http\Controllers\Api\TwoFactorController::class)->confirm2FA($request);
    }
}
