<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\KycProfile;
use App\Models\Wallet;
use App\Models\Demo\DemoWallet;
use App\Services\QoreidService;
use Exception;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Http;

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
            'profile_image' => 'nullable|image|max:10240',
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
                'trading_mode' => 'live'
            ]);

            if ($request->hasFile('profile_image')) {
                $path = $request->file('profile_image')->store('avatars', 'public');
                $user->profile_image = $path;
                $user->save();
            }

            // 💰 Step 3: Create LIVE Wallet for the user
            foreach (['NGN', 'USD'] as $curr) {
                Wallet::create([
                    'user_id' => $user->id,
                    'account_number' => 'XAV' . rand(10000000, 99999999),
                    'balance' => 0.00,
                    'ngn_cleared' => 0.00,
                    'ngn_uncleared' => 0.00,
                    'usd_cleared' => 0.00,
                    'usd_uncleared' => 0.00,
                    'locked' => 0.00,
                    'currency' => $curr,
                    'status' => 'active',
                ]);
            }

            // 💰 Create DEMO Wallet instantly so they can use demo mode immediately!
            foreach (['NGN', 'USD'] as $curr) {
                DemoWallet::create([
                    'user_id' => $user->id,
                    'account_number' => 'DEMO' . rand(10000000, 99999999),
                    'balance' => ($curr === 'NGN') ? 1000000.00 : 0.00,
                    'ngn_cleared' => ($curr === 'NGN') ? 1000000.00 : 0,
                    'usd_cleared' => ($curr === 'USD') ? 0.00 : 0,
                    'locked' => 0.00,
                    'currency' => $curr,
                    'status' => 'active',
                ]);
            }

            // 🔍 Step 4: Optional KYC verification (if user provided ID)
            $kycData = [];
            $status = 'pending';
            if (!empty($validated['id_type']) && !empty($validated['id_value'])) {
                // Attempt to verify identity via QoreID
                $verification = QoreidService::verifyIdentity(
                    $validated['id_type'],
                    $validated['id_value']
                );

                if (!empty($verification['success'])) {
                    $status = 'verified';
                    $kycData = $verification['data'] ?? [];

                    // Save verified KYC profile
                    KycProfile::create([
                        'user_id' => $user->id,
                        'level' => $kycData['level'] ?? 'basic',
                        'tier' => $kycData['tier'] ?? 1,
                        'daily_limit' => $kycData['daily_limit'] ?? 0,
                        'status' => $status,
                        'bvn' => $kycData['bvn'] ?? null,
                        'nin' => $kycData['nin'] ?? null,
                        'intl_passport' => $kycData['intl_passport'] ?? null,
                        'proof_of_address' => $kycData['proof_of_address'] ?? null,
                    ]);

                    // Save photo from KYC data if provided
                    if (!empty($kycData['photo'])) {
                        $photo = $kycData['photo'];
                        $photoContents = null;

                        if (filter_var($photo, FILTER_VALIDATE_URL)) {
                            try {
                                $response = Http::get($photo);
                                if ($response->successful()) {
                                    $photoContents = $response->body();
                                }
                            } catch (\Exception $e) {
                                // Could not download photo, continue without it
                            }
                        } elseif (str_starts_with($photo, 'data:image') || str_starts_with($photo, 'iVBOR')) {
                            $photoContents = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $photo));
                        }
                        if ($photoContents) {
                            $photoPath = 'photos/' . uniqid('user_') . '.png';
                            Storage::disk('public')->put($photoPath, $photoContents);

                            // If an avatar hasn't been set by the user's selfie upload,
                            // use the photo from the KYC provider as the avatar.
                            if (!$user->getAttributeValue('profile_image')) {
                                // Use direct assignment to bypass mass assignment protection
                                $user->profile_image = $photoPath;
                                $user->save();
                            }
                        }
                    }
                } else {
                    // KYC verification failed, but allow user to continue
                    // Create an empty KYC profile with pending status
                    KycProfile::create([
                        'user_id' => $user->id,
                        'level' => ($status === 'verified') ? 'basic' : 'none',
                        'tier' => 1,
                        'daily_limit' => 0,
                        'status' => $status,
                    ]);
                }
            } else {
                // No KYC data provided, create empty KYC profile
                KycProfile::create([
                    'user_id' => $user->id,
                    'level' => 'none',
                    'tier' => 1,
                    'daily_limit' => 0,
                    'status' => 'pending',
                ]);
            }

            DB::commit();


            try {
                ActivityLog::create([
                    'user_id'    => $user->id,
                    'activity'   => 'Registration',
                    'details'    => "New user registered: {$user->email}. Wallets generated and initial KYC processed.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
            }



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
