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
use App\Services\QoreidService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessKycVerification;

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
            'bvn' => 'nullable|string|digits:11',
            'nin' => 'nullable|string|digits:11',
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
                'trading_mode' => 'live',
            ]);
            if ($request->hasFile('profile_image')) {
                // Ensure file is stored before trying to get path
                $path = $request->file('profile_image')->store('avatars', 'public');
                $user->profile_image = $path;
                $user->save();

                // Refresh the user model to ensure it sees the saved path
                $user->refresh();
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
                    'ngn_uncleared' => 0.00,
                    'usd_cleared' => ($curr === 'USD') ? 0.00 : 0,
                    'usd_uncleared' => 0.00,
                    'locked' => 0.00,
                    'currency' => $curr,
                    'status' => 'active',
                ]);
            }

            // ₿ Step 4: Create Default Crypto Address (USDT-TRON)
            CryptoAddress::create([
                'user_id' => $user->id,
                'blockchain' => 'TRON',
                'address' => 'T' . \Illuminate\Support\Str::random(33), // Placeholder, actual gen via Tatum later
                'private_key' => encrypt('pending_generation'),
                'qr_code_url' => null,
            ]);

            DB::commit();

            // Create the initial KYC profile immediately so it exists
            $this->createPendingKyc($user, [
                'bvn' => $request->bvn,
                'nin' => $request->nin,
            ]);

            // Log activity
            ActivityLog::log($user->id, 'Registration', [
                'message' => "New user registered: {$user->email}. Wallets generated."
            ]);

            // 🔍 Step 5: KYC verification (Performed outside transaction to prevent rollback on external service failure)
            try {
                $rawImagePath = $user->getRawOriginal('profile_image');
                $hasBvn = $request->filled('bvn');
                $hasNin = $request->filled('nin');

                if (($hasBvn || $hasNin) && $rawImagePath) {
                    $disk = \Illuminate\Support\Facades\Storage::disk('public');

                    if ($disk->exists($rawImagePath)) {
                        $imagePath = $disk->path($rawImagePath);
                        
                        // Determine Tier: Tier 2 if both, Tier 1 if only one
                        $targetTier = ($hasBvn && $hasNin) ? 2 : 1;

                        // Dispatch background job to prevent request timeout
                        ProcessKycVerification::dispatch(
                            $user->id,
                            $request->bvn ?? '',
                            $request->nin ?? $request->bvn,
                            $rawImagePath,
                            $firstName,
                            $lastName
                        );
                    }
                }
            } catch (\Throwable $kycError) {
                Log::warning('KYC auto-verification failed during onboarding: ' . $kycError->getMessage());
            }

            // Generate authentication token
            $token = $user->createToken('xavier_token')->plainTextToken;

            $statusMessage = $user->kyc_status === 'verified' 
                ? 'Account created and verified successfully!' 
                : 'Account created. Identity verification is in progress.';

            return response()->json([
                'success' => true,
                'message' => $statusMessage,
                'kyc_status' => $user->kyc_status,
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

    /**
     * Set Tier 1 status and limits
     */
    private function finalizeVerification(User $user, $bvn, $nin, int $tier = 1)
    {
        $tierSettings = \App\Models\KycSetting::where('tier', $tier)->first();
        
        $level = match($tier) {
            1 => 'basic',
            2 => 'standard',
            3 => 'full',
            default => 'none'
        };
        
        $user->update(['kyc_status' => 'verified']);
        
        KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status'  => 'verified',
                'level'   => $level,
                'tier'    => $tier,
                'bvn'     => $bvn,
                'nin'     => $nin,
                'daily_limit' => $tierSettings ? $tierSettings->daily_limit : 500000,
            ]
        );

        ActivityLog::log($user->id, 'KYC Verified', ['message' => "User automatically verified and upgraded to Tier {$tier} via biometric match."]);
    }

    /**
     * Helper to create a pending KYC profile.
     */
    private function createPendingKyc(User $user, array $validated = []): void
    {
        KycProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'pending',
                'level'  => 'none',
                'bvn'    => $validated['bvn'] ?? null,
                'nin'    => $validated['nin'] ?? null,
                'tier'   => 0, // Default tier for pending KYC
                'rejection_reason' => null,
            ]
        );
    }
}
