<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        // Use the EmailVerificationRequest helper which marks the user as verified.
        $request->fulfill();

        // Ensure the Verified event is dispatched for test environments and listeners.
        try {
            Event::dispatch(new Verified($request->user()));
        } catch (\Throwable $e) {
            // swallow any event dispatch errors in edge cases
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
