<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserKyc;
use App\Models\Wallet;
use App\Services\QoreidService;
use Exception;
use App\Http\Resources\UserResource;

class OnboardingController extends Controller
{
    public function onboard(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'id_type' => 'nullable|in:bvn,vnin',
            'id_value' => 'nullable|string|max:20',
        ]);

        $nameInput = $request->input('name');
        $nameParts = explode(' ', $nameInput, 2);
        $firstName = trim($nameParts[0]);
        $lastName = trim($nameParts[1] ?? '');

        DB::beginTransaction();

        try {
            // 🔍 Step 1: Verify user identity (dummy or real QoreID)
            /* 
             $verification = QoreidService::verifyIdentity(
                $validated['id_type'],
                $validated['id_value']
            );

            if (empty($verification['success'])) {
                throw new Exception($verification['message'] ?? 'Verification failed');
            }

            $kycData = $verification['data'] ?? [];
            */

            // 🧍 Step 2: Create user account
            $user = User::create([
                'name' => $nameInput,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $validated['email'],
                //'phone' => $validated['phone'],
                //'dob' => $validated['dob'],
                'password' => Hash::make($validated['password']),
                'verified' => true,
            ]);
            DB::commit();
            /*
            // 🪪 Step 3: Save KYC data
            UserKyc::create([
                'user_id' => $user->id,
                'provider' => $verification['provider'] ?? 'QoreID-Dummy',
                'id_type' => $validated['id_type'],
                'id_value' => $validated['id_value'],
                'data' => json_encode($kycData),
            ]);

            // 💰 Step 4: Create Wallet for the user
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'account_number' => 'XAV' . rand(10000000, 99999999),
                'balance' => 0.00,
                'currency' => 'NGN',
                'status' => 'active',
            ]);

            // 🖼️ Step 5: Optional — Save photo (dummy or real)
            if (!empty($kycData['photo'])) {
                $photo = $kycData['photo'];
                if (str_starts_with($photo, 'data:image') || str_starts_with($photo, 'iVBOR')) {
                    $photoPath = 'photos/' . uniqid('user_') . '.png';
                    \Storage::disk('public')->put($photoPath, base64_decode($photo));
                    $user->update(['photo' => $photoPath]);
                }
            }

            DB::commit();

            $token = $user->createToken('xavier_token')->plainTextToken;
            return response()->json([
                'success' => true,
                'message' => 'Onboarding completed successfully',
                'token' => $token,
                'data' => new UserResource($user->load(['wallet', 'kyc'])),
            ], 200);
            */
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
