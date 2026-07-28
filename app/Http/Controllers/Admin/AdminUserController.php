<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $query = User::query()->with(['kyc', 'subscriptions.plan', 'roles']);

        // Support both 'search' and 'q' params
        $searchTerm = $request->search ?? $request->q;

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                  ->orWhere('last_name', 'like', "%{$searchTerm}%")
                  ->orWhere('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by account status
        if ($request->status === 'suspended') {
            $query->where('is_suspended', true);
        } elseif ($request->status === 'active') {
            $query->where('is_suspended', false);
        }

        // Filter by subscription status
        if ($request->subscription === 'trial') {
            $query->where('subscription_status', 'trial');
        } elseif ($request->subscription === 'active') {
            $query->where('subscription_status', 'active');
        }

        $perPage = (int) $request->get('per_page', 25);
        $users = $query->orderByDesc('created_at')->paginate($perPage);

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
                'roles' => $user->getRoleNames()->toArray(),
                'is_suspended' => $user->is_suspended,
                'subscription_status' => $user->subscription_status,
                'wallet_debt' => (float) $user->wallet_debt,
                'on_trial' => $user->on_trial,
                'tier' => $user->current_tier,
                'created_at' => $user->created_at,
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
            'roles',
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
                'roles' => $user->getRoleNames()->toArray(),
                'status' => $user->is_suspended ? 'suspended' : 'active',
                'is_suspended' => $user->is_suspended,
                'subscription_status' => $user->subscription_status ?? 'none',
                'wallet_debt' => (float) $user->wallet_debt,
                'created_at' => $user->created_at,
                'kyc' => $user->kyc,
                'google2fa_enabled' => $user->google2fa_enabled,
            ],
            'wallet' => [
                'ngn' => $user->wallets->where('currency', 'NGN')->sum(fn ($w) => $w->ngn_cleared + $w->ngn_uncleared),
                'usd' => $user->wallets->where('currency', 'USD')->sum(fn ($w) => $w->usd_cleared + $w->usd_uncleared),
            ],
            'subscriptions' => $user->subscriptions->map(fn ($s) => [
                'id' => $s->id,
                'plan' => $s->plan,
                'status' => $s->status,
                'starts_at' => $s->starts_at,
                'expires_at' => $s->expires_at,
            ]),
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

    /**
     * Assign roles to a user.
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|in:user,admin,super-admin,accounts,compliance,manager,support',
        ]);

        // Sync roles
        $user->syncRoles($validated['roles']);

        // Set primary role as the first role or default to 'user'
        $user->role = $validated['roles'][0] ?? 'user';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Roles updated successfully',
            'roles' => $user->getRoleNames()->toArray(),
        ]);
    }
}
