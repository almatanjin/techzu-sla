<?php

namespace App\Http\Requests\Admin;

use App\Data\UserFilterData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['nullable', 'string', Rule::enum(UserRole::class)],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toData(): UserFilterData
    {
        $roleInput = $this->validated('role');
        $role = $roleInput !== null ? UserRole::tryFrom((string) $roleInput) : null;

        $statusInput = $this->validated('status');
        $isActive = match ($statusInput) {
            'active' => true,
            'inactive' => false,
            default => null,
        };

        $searchInput = $this->validated('search');
        $search = is_string($searchInput) && trim($searchInput) !== '' ? trim($searchInput) : null;

        return new UserFilterData(
            role: $role,
            isActive: $isActive,
            search: $search,
        );
    }
}

