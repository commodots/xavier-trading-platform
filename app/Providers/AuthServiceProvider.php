<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * Gate for superadmin access (prevent privilege escalation).
         */
        Gate::define('superadmin', function (User $user): bool {
            return $user->hasRole('superadmin');
        });

        /**
         * Gate for staff access (support, analyst, moderator).
         */
        Gate::define('staff', function (User $user): bool {
            return $user->hasRole('superadmin','support');
        });

        /**
         * Gate for verified trading access (KYC + email verified).
         */
        Gate::define('can-trade', function (User $user): bool {
            return $user->kyc_verified === true
                && $user->email_verified_at !== null
                && $user->status !== 'disabled';
        });

        /**
         * Gate for admin view access.
         */
        Gate::define('view-admin', function (User $user): bool {
            return $user->hasRole('superadmin', 'support');
        });
    }
}
