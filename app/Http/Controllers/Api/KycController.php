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
            'bvn' => 'required_without:nin|nullable|string|size:11',
            'nin' => 'nullable|string|size:11',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $user = Auth::user();

// If they have both, it's 'full'. If only BVN, it's 'basic'.
        $level = ($request->bvn && $request->nin) ? 'full' : 'basic';

        $idType = $request->nin ? 'nin' : 'bvn';
        $idNumber = $request->nin ?? $request->bvn;

        $kyc = KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id_type'   => $idType,
            'id_number' => $idNumber,
                'bvn' => $request->bvn, 
                'nin' => $request->nin,
                'status' => 'pending',
                'level'     => $level,
            ]
        );

        $paths = [];

        if ($request->hasFile('photo')) {
            $paths['id_front'] = $request->file('photo')->store('kyc/id_fronts', 'public');
        }
        if ($request->hasFile('document')) {
            $paths['proof'] = $request->file('document')->store('kyc/proofs', 'public');
        }
        if (!empty($paths)) {
            $kyc->update($paths);
        }


        return response()->json([
            'success' => true,
            'message' => "KYC submitted successfully as $level verification!",
            'data' => $kyc,
        ]);
    }

    /**
     * Get KYC info for authenticated user
     */
    public function show() {
    $user = Auth::user();
    $kyc = KycProfile::where('user_id', $user->id)->first(); 
    return response()->json(['success' => true, 'data' => $kyc]);
    }

    public function submit(Request $request)
    {
        ActivityLog::log(Auth::id(), 'KYC Submission', ['level' => $level]);
        return $this->update($request);
    }
}
