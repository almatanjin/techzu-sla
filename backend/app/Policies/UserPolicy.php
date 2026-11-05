<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function searchCustomers(User $user): bool
    {
        return ($user->isAdmin() || $user->isAgent()) && (bool) $user->is_active;
    }
}

