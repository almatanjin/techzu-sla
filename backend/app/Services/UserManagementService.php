<?php

namespace App\Services;

use App\Data\CreateUserData;
use App\Data\UpdateUserData;
use App\Data\UserFilterData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function list(UserFilterData $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query();

        if ($filters->role !== null) {
            $query->where('role', $filters->role->value);
        }

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        if ($filters->search !== null && $filters->search !== '') {
            $search = $filters->search;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest('id')->paginate($perPage);
    }

    public function create(CreateUserData $data): User
    {
        $user = new User([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);
        $user->role = $data->role;
        $user->is_active = $data->isActive;
        $user->save();

        return $user;
    }

    public function update(User $target, UpdateUserData $data, User $actor): User
    {
        if ($actor->id === $target->id) {
            if ($data->hasRole && $data->role !== $target->role) {
                throw ValidationException::withMessages([
                    'role' => ['You cannot change your own role.'],
                ]);
            }

            if ($data->hasIsActive && ! $data->isActive) {
                throw ValidationException::withMessages([
                    'is_active' => ['You cannot deactivate your own account.'],
                ]);
            }
        }

        return DB::transaction(function () use ($target, $data): User {
            $shouldRevokeTokens = false;

            if ($data->hasName && $data->name !== null) {
                $target->name = $data->name;
            }

            if ($data->hasEmail && $data->email !== null) {
                $target->email = $data->email;
            }

            if ($data->hasRole && $data->role !== null) {
                $target->role = $data->role;
            }

            if ($data->hasIsActive && $data->isActive !== null) {
                if ($target->is_active && ! $data->isActive) {
                    $shouldRevokeTokens = true;
                }
                $target->is_active = $data->isActive;
            }

            if ($data->hasPassword && $data->password !== null) {
                $target->password = $data->password;
                $shouldRevokeTokens = true;
            }

            $target->save();

            if ($shouldRevokeTokens) {
                $target->tokens()->delete();
            }

            return $target;
        });
    }
}
