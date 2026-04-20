<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
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
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB
            'proof_of_address' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $user = Auth::user();

        $updateData = [
            'user_id' => $user->id,
            'id_type' => $request->id_type,
            'id_number' => $request->id_number,
            'status' => 'pending'
        ];

        if ($request->hasFile('document')) {
            $column = match ($request->id_type) {
                'passport' => 'intl_passport',
                'dl' => 'drivers_license',
                'id_card' => 'national_id',
                default => 'id_number'
            };
            $updateData[$column] = $request->file('document')->store('kyc/docs', 'public');
        }

        if ($request->hasFile('proof_of_address')) {
            $updateData['proof_of_address'] = $request->file('proof_of_address')->store('kyc/address', 'public');
        }

        $kyc = KycProfile::updateOrCreate(['user_id' => $user->id], $updateData);

        return response()->json(['success' => true, 'message' => 'Documents submitted for review.', 'data' => $kyc]);
    }

    /**
     * Get KYC info for authenticated user
     */
    public function show()
    {
        $user = Auth::user();
        $kyc = KycProfile::where('user_id', $user->id)->firstOrFail();
        
        // Explicitly format data for the frontend to show "Inputed details"
        $data = $kyc->toArray();
        
        // Mask sensitive data for display (Assumes raw storage for now)
        if ($kyc->bvn) {
            $data['bvn_display'] = '*******' . substr($kyc->bvn, -4);
        }
        if ($kyc->nin) {
            $data['nin_display'] = '*******' . substr($kyc->nin, -4);
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
