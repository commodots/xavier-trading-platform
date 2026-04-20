<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\KycProfile;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QoreidWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Security: Validate QoreID signature or IP if possible
        Log::info('QoreID Webhook received', $request->all());

        $event = $request->input('event'); // e.g., 'verification.completed'
        $status = $request->input('status'); // 'VERIFIED' or 'FAILED'
        $reference = $request->input('reference'); // This should be our User ID or a custom Ref

        $user = User::find($reference);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($status === 'VERIFIED') {
            $tier1 = \App\Models\KycSetting::where('tier', 1)->first();
            
            $user->update(['kyc_status' => 'verified']);
            $user->kyc()->update([
                'status' => 'verified',
                'tier' => 1,
                'daily_limit' => $tier1 ? $tier1->daily_limit : 500000,
            ]);

            ActivityLog::log($user->id, 'KYC Webhook Success', ['details' => 'Verification confirmed via QoreID webhook.']);
        } else {
            $user->update(['kyc_status' => 'failed']);
            $user->kyc()->update(['status' => 'rejected', 'rejection_reason' => $request->input('reason', 'Verification failed')]);
            
            ActivityLog::log($user->id, 'KYC Webhook Failed', ['details' => 'Verification failed via QoreID webhook.']);
        }

        return response()->json(['success' => true]);
    }
}
