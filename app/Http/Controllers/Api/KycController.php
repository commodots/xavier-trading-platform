<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ProcessKycVerification;
use App\Models\KycProfile;
use App\Models\ActivityLog;

class KycController extends Controller
{
    /**
     * Update or create user KYC record
     */
    public function update(Request $request)
    {
        $request->validate([
            'id_type' => 'required|in:bvn,nin,tin,passport,dl,id_card',
            'id_number' => 'required|string',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', 
            'profile_image' => 'nullable|file|mimes:jpg,jpeg,png|max:5120', // Matches Vue form key name
            'proof_of_address' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $user = Auth::user();

        // Force a clear pending baseline so frontend triggers polling loop
        $updateData = [
            'user_id' => $user->id,
            'id_type' => $request->id_type,
            'id_number' => $request->id_number,
            'status' => 'pending', 
            'verified_at' => null // Clear any stale verification dates
        ];

        // Handle standard registration selfie stream or formal file documents
        if ($request->hasFile('profile_image')) {
            $updateData['profile_photo_path'] = $request->file('profile_image')->store('kyc/biometrics', 'public');
        }

        if ($request->hasFile('document')) {
            $column = match ($request->id_type) {
                'passport' => 'intl_passport',
                'dl' => 'drivers_license',
                'id_card' => 'national_id',
                default => 'id_number_file'
            };
            $updateData[$column] = $request->file('document')->store('kyc/docs', 'public');
        }

        if ($request->hasFile('proof_of_address')) {
            $updateData['proof_of_address'] = $request->file('proof_of_address')->store('kyc/address', 'public');
        }

        $kyc = KycProfile::updateOrCreate(['user_id' => $user->id], $updateData);

        if ($kyc->profile_photo_path && ($kyc->bvn || $kyc->nin)) {
            ProcessKycVerification::dispatch(
                $user->id,
                $kyc->bvn ?? '',
                $kyc->nin ?? $kyc->bvn ?? '',
                $kyc->profile_photo_path,
                $user->first_name,
                $user->last_name
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Identity profile locked. Verification sequence started.',
            'data' => $kyc
        ]);
    }

    /**
     * Get KYC info for authenticated user
     */
    public function show()
    {
        $user = Auth::user();
        $kyc = KycProfile::where('user_id', $user->id)->firstOrFail();
        
        $data = $kyc->toArray();
        
        // Dynamically mask digits via standard string manipulation
        if (!empty($kyc->id_number)) {
            $data['id_number_display'] = '*******' . substr($kyc->id_number, -4);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function submit(Request $request)
    {
        $level = $request->input('level', null);
        ActivityLog::log(Auth::id(), 'KYC Submission', ['level' => $level]);
        return $this->update($request);
    }
}