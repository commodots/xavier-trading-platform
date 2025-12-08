<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Kyc;

class KycController extends Controller
{
    /**
     * Update or create user KYC record
     */
    public function update(Request $request)
    {
        $request->validate([
            'id_type' => 'required|string',
            'id_value' => 'required|string',
            'id_front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'id_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $user = Auth::user();

        $kyc = Kyc::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id_type' => $request->id_type,
                'id_value' => $request->id_value,
                'status' => 'pending',
            ]
        );

        $paths = [];

        if ($request->hasFile('id_front')) {
            $paths['id_front'] = $request->file('id_front')->store('kyc/id_fronts', 'public');
        }
        if ($request->hasFile('id_back')) {
            $paths['id_back'] = $request->file('id_back')->store('kyc/id_backs', 'public');
        }
        if ($request->hasFile('proof')) {
            $paths['proof'] = $request->file('proof')->store('kyc/proofs', 'public');
        }

        $kyc->update($paths);

        return response()->json([
            'success' => true,
            'message' => 'KYC information submitted successfully! Pending verification.',
            'data' => $kyc,
        ]);
    }

    /**
     * Get KYC info for authenticated user
     */
    public function show()
    {
        $user = Auth::user();
        $kyc = Kyc::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => $kyc,
        ]);
    }
}
