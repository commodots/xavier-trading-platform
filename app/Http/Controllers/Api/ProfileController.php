<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserKyc;
use App\Models\SystemSetting;
use App\Models\KycSetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\ActivityLog;
use App\Services\StaffPermissionService;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user()->load(['kyc', 'linkedAccounts']);
        $user->name = $user->name ?: trim($user->first_name . ' ' . $user->last_name);

        // attach system base currency and tier-based daily_limit to the kyc payload so frontend can format limits
        $settings = SystemSetting::first();
        $baseCurrency = $settings->base_currency ?? 'NGN';
        if ($user->kyc) {
            // determine tier (default to 1)
            $tier = (int) ($user->kyc->tier ?? 1);
            $kycSetting = KycSetting::getByTier($tier);
            if ($kycSetting) {
                $user->kyc->daily_limit = $kycSetting->daily_limit;
            }
            $user->kyc->currency = $user->kyc->currency ?? $baseCurrency;
        }

        // Get the primary role (prefer non-admin roles for staff)
        $roleNames = $user->getRoleNames();
        $primaryRole = $roleNames->reject(fn($r) => $r === 'admin')->first() ?? $roleNames->first() ?? $user->role ?? 'user';
        $user->role = $primaryRole;

        // Attach staff permissions if not admin
        $isAdmin = $user->hasRole('admin');
        if (!$isAdmin) {
            $permissions = [];
            foreach (StaffPermissionService::CAPABILITIES as $cap) {
                $permissions[$cap] = StaffPermissionService::roleHasCapability($user, $cap);
            }
            $user->permissions = $permissions;
        }

        return response()->json([
            'success' => true,
            'data' => $user
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

    public function submitKyc(Request $r)
    {
        $r->validate([
            'id_type' => 'required|string',
            'id_value' => 'required|string',
            'photo' => 'nullable|image|max:2048',
            'document' => 'nullable|file|max:5120'
        ]);

        $user = Auth::user();

        $kyc = UserKyc::updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => 'dummy',
                'id_type' => $r->id_type,
                'id_value' => $r->id_value,
                'status' => 'pending'
            ]
        );

        if ($r->hasFile('photo')) {
            $kyc->photo_path = $r->file('photo')->store('kyc/photos', 'public');
        }
        if ($r->hasFile('document')) {
            $kyc->document_path = $r->file('document')->store('kyc/docs', 'public');
        }
        $kyc->save();

        // optionally trigger async verification job here
        return response()->json(['success' => true, 'message' => 'KYC submitted', 'data' => $kyc]);
    }

    public function getKyc(Request $r)
    {
        $user = Auth::user();
        $kyc = UserKyc::where('user_id', $user->id)->first();
        return response()->json(['success' => true, 'data' => $kyc]);
    }
}
