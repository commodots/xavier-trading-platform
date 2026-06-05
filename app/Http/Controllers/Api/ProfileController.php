<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\KycProfile;
use App\Models\SystemSetting;
use App\Models\KycSetting;
use App\Models\ActivityLog;
use App\Services\StaffPermissionService;
use App\Services\KycService;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user()->load(['kyc', 'linkedAccounts', 'wallet', 'demoWallet', 'roles']);
        
        $user->name = $user->name ?: trim($user->first_name . ' ' . $user->last_name);

        $settings = SystemSetting::first();
        $baseCurrency = $settings->base_currency ?? 'NGN';
        
        if ($user->kyc) {
            // determine tier (default to 1)
            $tier = (int) ($user->kyc->tier ?? 1);
            $kycSetting = KycSetting::where('tier', $tier)->first();
            if ($kycSetting) {
                $user->kyc->daily_limit = $kycSetting->daily_limit;
            }
            $user->kyc->currency = $user->kyc->currency ?? $baseCurrency;
        }

        // Attach permissions for EVERYONE (Admins get all true, Staff get calculated)
        $permissions = [];
        $isSystemAdmin = $user->hasRole('admin');

        foreach (StaffPermissionService::CAPABILITIES as $cap) {
            // If admin, they get 'true' for everything automatically
            $permissions[$cap] = $isSystemAdmin ? true : StaffPermissionService::roleHasCapability($user, $cap);
        }

        // Determine the primary display role
        $roleNames = $user->getRoleNames();
        $displayRole = $roleNames->first();

        // If no explicit role is assigned but they have permissions, label accordingly for UI logic
        if (!$displayRole) {
            $displayRole = ($permissions['manage_system_settings'] ?? false) ? 'admin' : 
                           (collect($permissions)->contains(true) ? 'staff' : 'user');
        }

        $user->permissions = $permissions;

        return response()->json([
            'success' => true,
            'data' => array_merge($user->toArray(), ['role' => $displayRole])
        ]);
    }

    public function update(Request $r)
    {
        $user = Auth::user();

        $r->validate([
            'email' => 'required|email|unique:users,email,' . $user->id,
            'name' => 'required|string|max:255',
        ]);

        $data = $r->only(['email', 'phone', 'address']);

        $parts = explode(' ', $r->name, 2);
        $data['first_name'] = $parts[0];
        $data['last_name'] = $parts[1] ?? '';

        $data['name'] = $r->name;

        $user->update($data);

        ActivityLog::log(Auth::id(), 'Profile Update');

        return response()->json([
            'success' => true,
            'data' => $user->fresh()
        ]);
    }

    /**
     * Submit KYC verification documents
     * Sets status to 'pending' and triggers Dojah verification process
     */
    public function submitKyc(Request $r)
    {
        $r->validate([
            'bvn' => 'nullable|string|size:11',
            'nin' => 'nullable|string|size:11',
            'tin' => 'nullable|string',
            'id_type' => 'nullable|string|in:intl_passport,national_id,drivers_license,voters_card,nin_slip,proof_of_address',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'
        ]);

        // Require at least BVN or NIN
        if (!$r->filled('bvn') && !$r->filled('nin')) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide at least BVN or NIN to begin verification.'
            ], 422);
        }

        $user = Auth::user();

        $updateData = [
            'status' => 'pending',
            'verified_at' => null, // Clear stale verification dates
        ];

        // Only update fields that are provided
        if ($r->filled('bvn')) {
            $updateData['bvn'] = $r->bvn;
        }
        if ($r->filled('nin')) {
            $updateData['nin'] = $r->nin;
        }
        if ($r->filled('tin')) {
            $updateData['tin'] = $r->tin;
        }
        if ($r->filled('id_type')) {
            $updateData['id_type'] = $r->id_type;
        }

        $kyc = KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            $updateData
        );

        // Store uploaded documents
        if ($r->hasFile('photo')) {
            $kyc->photo = $r->file('photo')->store('kyc/photos', 'public');
        }

        if ($r->hasFile('document')) {
            if ($r->id_type === 'intl_passport') {
                $kyc->intl_passport = $r->file('document')->store('kyc/docs', 'public');
            } elseif ($r->id_type === 'national_id') {
                $kyc->national_id = $r->file('document')->store('kyc/docs', 'public');
            } elseif ($r->id_type === 'drivers_license') {
                $kyc->drivers_license = $r->file('document')->store('kyc/docs', 'public');
            } else {
                $kyc->id_number = $r->file('document')->store('kyc/docs', 'public');
            }
        }

        $kyc->save();

        // Update user kyc_status to indicate verification is pending
        $user->update(['kyc_status' => 'pending']);

        // Log the KYC submission
        ActivityLog::log($user->id, 'KYC Submission Started', [
            'bvn_masked' => KycService::maskPii($kyc->bvn),
            'nin_masked' => KycService::maskPii($kyc->nin),
        ]);

        if ($kyc->photo && ($kyc->bvn || $kyc->nin)) {
            ProcessKycVerification::dispatch(
                $user->id,
                $kyc->bvn ?? '',
                $kyc->nin ?? $kyc->bvn ?? '',
                $kyc->photo,
                $user->first_name,
                $user->last_name
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification started. Please wait while we validate your identity.',
            'data' => $kyc->toFormattedArray()
        ]);
    }

    /**
     * Get KYC data with masked PII for safe frontend display
     */
    public function getKyc(Request $r)
    {
        $user = Auth::user();
        $kyc = KycProfile::where('user_id', $user->id)->first();

        if (!$kyc) {
            return response()->json([
                'success' => true,
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $kyc->toFormattedArray()
        ]);
    }

    /**
     * Get full KYC data (unmasked) - Backend/Admin only
     * This endpoint should be protected by additional authorization checks
     */
    public function getKycFull(Request $r)
    {
        $user = Auth::user();
        $kyc = KycProfile::where('user_id', $user->id)->first();

        if (!$kyc) {
            return response()->json([
                'success' => true,
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $kyc->toArray()
        ]);
    }

    public function switchMode(Request $request)
    {
        $user = Auth::user();
        $mode = $request->mode === 'demo' ? 'demo' : 'live';
        
        $user->update(['trading_mode' => $mode]);

        return response()->json([
            'success' => true,
            'message' => "Switched to " . strtoupper($mode) . " mode",
            'trading_mode' => $mode,
            'user' => $user->load(['wallet', 'demoWallet']) 
        ]);
    }
}
