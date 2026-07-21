<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'manager']) || $user->hasPermissionTo('view reports');
    }

    public function view(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'manager']) || $user->hasPermissionTo('view reports');
    }

    public function export(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $user->hasPermissionTo('export reports');
    }

    public function viewFinancial(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'accounts']) || $user->hasPermissionTo('view financial reports');
    }

    public function viewSystem(User $user): bool
    {
        return $user->hasRole(['super-admin']) || $user->hasPermissionTo('view system reports');
    }

    public function maintenance(User $user): bool
    {
        return $user->hasRole(['super-admin']);
    }
}