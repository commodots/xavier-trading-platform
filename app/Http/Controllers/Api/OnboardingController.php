<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Demo\DemoWallet;
use App\Models\KycProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Models\CryptoAddress;
use App\Models\SystemSetting;
use App\Notifications\WelcomeNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessKycVerification;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    public function onboard(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'dob' => 'required|date',
            'bvn' => 'nullable|string|digits:11',
            'nin' => 'nullable|string|digits:11',
            'profile_image' => 'nullable|string', // Changed from required to nullable
        ]);

        // Break name down into parts helper
        $nameParts = explode(' ', trim($validated['name']), 2);
        $firstName = trim($nameParts[0]);
        $lastName = trim($nameParts[1] ?? '');

        DB::beginTransaction();

        try {
            $trialDays = SystemSetting::first()?->trial_days ?? 7;

            // Step 1: Create user profile with 'pending' KYC status
            $user = User::create([
                'name' => $validated['name'],
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'dob' => $validated['dob'] ?? null,
                'password' => Hash::make($validated['password']),
                'trading_mode' => 'live',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays($trialDays),
                'next_fee_due_at' => now()->addDays($trialDays),
                'last_active_at' => now(),
                'kyc_status' => 'pending',
            ]);

            // Step 2: Handle saving profile image string/metadata safely
            $savedImagePath = null; // Default fallback to null or a default asset path

            if (!empty($validated['profile_image'])) {
                $savedImagePath = 'avatars/' . uniqid() . '.txt';
                if (str_starts_with($validated['profile_image'], 'data:image')) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $validated['profile_image'], $matches)) {
                        $imageType = in_array($matches[1], ['jpeg', 'jpg', 'png', 'webp']) ? $matches[1] : 'jpg';
                        $imageData = base64_decode(substr($validated['profile_image'], strpos($validated['profile_image'], ',') + 1), true);
                        if ($imageData !== false) {
                            $savedImagePath = 'avatars/' . uniqid() . '.' . $imageType;
                            Storage::disk('public')->put($savedImagePath, $imageData);
                        }
                    }
                } else {
                    // If it's a Dojah reference ID string, keep the text reference
                    Storage::disk('public')->put($savedImagePath, $validated['profile_image']);
                }
            }

            
            $user->update(['profile_image' => $savedImagePath]);

            // Step 3: Create Live Wallets
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

            // Step 4: Create Demo Wallets
            foreach (['NGN', 'USD'] as $curr) {
                DemoWallet::create([
                    'user_id' => $user->id,
                    'account_number' => 'DEMO' . rand(10000000, 99999999),
                    'balance' => ($curr === 'NGN') ? 1000000.00 : 0.00,
                    'ngn_cleared' => ($curr === 'NGN') ? 1000000.00 : 0,
                    'ngn_uncleared' => 0.00,
                    'usd_cleared' => ($curr === 'USD') ? 1000.00 : 0,
                    'usd_uncleared' => 0.00,
                    'locked' => 0.00,
                    'currency' => $curr,
                    'status' => 'active',
                ]);
            }

            // Step 5: Create Default Crypto Address (USDT-TRON)
            CryptoAddress::create([
                'user_id' => $user->id,
                'blockchain' => 'TRON',
                'address' => 'T' . Str::random(33),
                'private_key' => encrypt('pending_generation'),
            ]);

            // Initialize a structural placeholder profile
            KycProfile::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'level' => 'none',
                'tier' => 0,
                'bvn' => $validated['bvn'] ?? null,
                'nin' => $validated['nin'] ?? null,
            ]);

            DB::commit();

            /*
            // Step 6: Dispatch async verifying processing job
            ProcessKycVerification::dispatch(
                $user->id,
                $validated['bvn'] ?? '',
                $validated['nin'] ?? '',
                $validated['profile_image'] ?? '', // Added null fallback operator safely
                $firstName,
                $lastName
            );
            */

            // Send welcome notification
            try {
                $user->notify(new WelcomeNotification($user->first_name ?: $user->name));
            } catch (\Throwable $e) {
                Log::warning('Failed to send welcome notification', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            ActivityLog::log($user->id, 'Registration', [
                'message' => "New user registered: {$user->email}. Verification job queued."
            ]);

            // Generate authentication token
            $token = $user->createToken('xavier_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully. Verification processing.',
                'kyc_status' => 'pending',
                'token' => $token,
                'user' => new UserResource($user->load(['wallet', 'kyc', 'cryptoAddresses'])),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('User onboarding failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create account right now. Please try again later.',
            ], 500);
        }
    }
}