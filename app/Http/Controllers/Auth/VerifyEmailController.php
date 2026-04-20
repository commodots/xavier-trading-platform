<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        // Validate the hash manually as a secondary security measure
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return $this->handleResponse($request, 'Invalid verification link.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->handleResponse($request, 'Email already verified.', 200, true);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->handleResponse($request, 'Email verified successfully.', 200, true);
    }

    protected function handleResponse($request, $message, $code, $success = false)
    {
        // Redirect to your frontend URL explicitly
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => $success, 'message' => $message], $code);
        }

        $status = $success ? '1' : '0';
        return redirect($frontendUrl . "/welcome?verified={$status}&message=" . urlencode($message));
    }
}