<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('Verification Attempt Started', ['id' => $request->route('id')]);

        $user = User::find($request->route('id'));

        if (!$user) {
            Log::error('User not found in verification');
            return $this->handleResponse($request, 'User not found.', 404);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->handleResponse($request, 'Email already verified.', 200, true);
        }

        // Bypassing all lifecycle hooks/observers that might be blocking the save
        $updated = DB::table('users')
            ->where('id', $user->id)
            ->update(['email_verified_at' => now()]);

        Log::info('Database Update Result', ['success' => $updated]);

        if ($updated) {
            event(new Verified($user));
        }

        return $this->handleResponse($request, 'Email verified successfully.', 200, true);
    }

    protected function handleResponse($request, $message, $code, $success = false)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => $success, 'message' => $message], $code);
        }

        $status = $success ? '1' : '0';
        
        // Use a "no-cache" redirect to ensure the browser doesn't skip the request next time
        return redirect($frontendUrl . "/welcome?verified={$status}&message=" . urlencode($message))
            ->withHeaders([
                'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate',
                'Pragma'        => 'no-cache',
                'Expires'       => 'Fri, 01 Jan 1990 00:00:00 GMT',
            ]);
    }
}