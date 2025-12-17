<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        // Step 1: Validate registration data (KYC fields are optional)
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
            // 🧍 Step 2: Create user account first (required)
            $user = User::create([
                'name' => $nameInput,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'dob' => $validated['dob'] ?? null,
                'password' => Hash::make($validated['password']),
                'verified' => false, // Email not verified yet
            ]);

            // 💰 Step 3: Create Wallet for the user
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'account_number' => 'XAV' . rand(10000000, 99999999),
                'balance' => 0.00,
                'currency' => 'NGN',
                'status' => 'active',
            ]);

            // 🔍 Step 4: Optional KYC verification (if user provided ID)
            $kycData = [];
            if (!empty($validated['id_type']) && !empty($validated['id_value'])) {
                // Attempt to verify identity via QoreID
                $verification = QoreidService::verifyIdentity(
                    $validated['id_type'],
                    $validated['id_value']
                );


                if (!empty($verification['success'])) {
                    $kycData = $verification['data'] ?? [];
                    
                    // Save verified KYC data
                    UserKyc::create([
                        'user_id' => $user->id,
                        'provider' => $verification['provider'] ?? 'QoreID',
                        'id_type' => $validated['id_type'],
                        'id_value' => $validated['id_value'],
                        'data' => json_encode($kycData),
                        'verified_at' => now(),
                    ]);

                    // 🖼️ Save photo from KYC data
                    if (!empty($kycData['photo'])) {
                        $photo = $kycData['photo'];
                        if (str_starts_with($photo, 'data:image') || str_starts_with($photo, 'iVBOR')) {
                            $photoPath = 'photos/' . uniqid('user_') . '.png';
                            Storage::disk('public')->put($photoPath, base64_decode($photo));
                            $user->update(['photo' => $photoPath]);
                        }
                    }
                } else {
                    // KYC verification failed, but allow user to continue
                    // Create a record with pending status
                    UserKyc::create([
                        'user_id' => $user->id,
                        'provider' => 'manual',
                        'id_type' => $validated['id_type'],
                        'id_value' => $validated['id_value'],
                        'data' => null,
                    ]);
                }
            } else {
                // No KYC data provided, create empty record
                UserKyc::create([
                    'user_id' => $user->id,
                    'provider' => 'manual',
                ]);
            }

            DB::commit();

            // Generate authentication token
            $token = $user->createToken('xavier_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'token' => $token,
                'user' => new UserResource($user->load(['wallet', 'kyc'])),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
