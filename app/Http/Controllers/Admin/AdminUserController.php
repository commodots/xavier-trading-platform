<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * List users with search, pagination.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Support both 'search' and 'q' params 
        $searchTerm = $request->search ?? $request->q;

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by status
        if ($request->status === 'suspended') {
            $query->where('is_suspended', true);
        } elseif ($request->status === 'active') {
            $query->where('is_suspended', false);
        }

        // Filter by subscription status
        if ($request->subscription === 'trial') {
            $query->trial();
        } elseif ($request->subscription === 'active') {
            $query->paying();
        }

        $users = $query->with(['kyc', 'subscriptions'])
            ->orderByDesc('created_at')
            ->paginate(20);

        
        $items = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->is_suspended ? 'suspended' : 'active',
                'role' => $user->role,
                'roles' => $user->getRoleNames(),
                'is_suspended' => $user->is_suspended,
                'created_at' => $user->created_at,
                'on_trial' => $user->on_trial,
                'tier' => $user->current_tier,
            ];
        });

        return response()->json([
            'success' => true,
            'users' => $items,
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single user with relations.
     */
    public function show(User $user)
    {
        $user->load([
            'kyc',
            'wallets',
            'subscriptions.plan',
            'riskFlags',
            'devices',
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'roles' => $user->getRoleNames(),
                'status' => $user->is_suspended ? 'suspended' : 'active',
                'is_suspended' => $user->is_suspended,
                'subscription_status' => $user->subscription_status,
                'wallet_debt' => (float) $user->wallet_debt,
                'created_at' => $user->created_at,
                'kyc' => $user->kyc,
                'google2fa_enabled' => $user->google2fa_enabled,
            ],
            'wallet' => [
                'ngn' => $user->wallets->where('currency', 'NGN')->sum(fn ($w) => $w->ngn_cleared + $w->ngn_uncleared),
                'usd' => $user->wallets->where('currency', 'USD')->sum(fn ($w) => $w->usd_cleared + $w->usd_uncleared),
            ],
            'transactions' => $user->transactions()->latest()->take(20)->get(),
            'devices' => $user->devices()->orderByDesc('last_active_at')
                ->get(['device_name', 'ip_address', 'last_active_at', 'is_trusted']),
        ]);
    }

    /**
     * Suspend a user account.
     */
    public function suspend(User $user, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user->update([
            'is_suspended' => true,
            'suspension_reason' => $validated['reason'],
        ]);

        // Revoke all tokens (force logout)
        $user->tokens()->delete();

        return response()->json([
            'message' => 'User suspended and all sessions revoked',
        ]);
    }

    /**
     * Unsuspend a user account.
     */
    public function unsuspend(User $user): JsonResponse
    {
        $user->update([
            'is_suspended' => false,
            'suspension_reason' => null,
        ]);

        return response()->json([
            'message' => 'User unsuspended',
        ]);
    }

    /**
     * Force logout — revoke all tokens.
     */
    public function forceLogout(User $user): JsonResponse
    {
        $user->tokens()->delete();

        return response()->json([
            'message' => 'All sessions revoked',
        ]);
    }

    /**
     * Reset user's 2FA.
     */
    public function reset2FA(User $user): JsonResponse
    {
        $user->update([
            'google2fa_enabled' => false,
            'google2fa_secret' => null,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication has been reset',
        ]);
    }
}