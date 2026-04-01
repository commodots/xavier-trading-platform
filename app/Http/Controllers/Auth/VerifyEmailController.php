<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request)
    {
        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->verifyApi($request);
        }

        // Handle web requests
        return $this->verifyWeb($request);
    }

    /**
     * Handle web email verification.
     */
    protected function verifyWeb(Request $request): RedirectResponse
    {
        $emailVerificationRequest = new EmailVerificationRequest($request);

        if ($emailVerificationRequest->user()->hasVerifiedEmail()) {
            return redirect('/email-verified');
        }

        $emailVerificationRequest->fulfill();

        return redirect('/email-verified');
    }

    /**
     * Handle API email verification.
     */
    protected function verifyApi(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            $user = \App\Models\User::find($request->route('id'));
        }

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
        }

        if (! hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link.',
            ], 400);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link.',
            ], 400);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }
}
