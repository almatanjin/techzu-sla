<?php

namespace App\Http\Requests\Admin;

use App\Data\UpdateUserData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => strtolower(trim($this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $targetRole = $this->has('role')
            ? $this->input('role')
            : ($target instanceof User ? ($target->role instanceof UserRole ? $target->role->value : (string) $target->role) : null);

        $isAgent = $targetRole === UserRole::Agent->value;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target),
            ],
            'role' => ['sometimes', 'string', Rule::enum(UserRole::class)],
            'password' => ['sometimes', 'nullable', 'string', Password::min(8)->letters()->numbers()],
            'is_active' => ['sometimes', 'boolean'],
            'is_available' => $isAgent ? ['sometimes', 'boolean'] : ['prohibited'],
            'max_open_tickets' => $isAgent ? ['sometimes', 'nullable', 'integer', 'between:1,1000'] : ['prohibited'],
            'category_ids' => $isAgent ? ['sometimes', 'array'] : ['prohibited'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    public function toData(): UpdateUserData
    {
        $hasName = $this->has('name');
        $hasEmail = $this->has('email');
        $hasRole = $this->has('role');
        $hasPassword = $this->filled('password');
        $hasIsActive = $this->has('is_active');
        $hasIsAvailable = $this->has('is_available');
        $hasMaxOpenTickets = $this->has('max_open_tickets');
        $hasCategoryIds = $this->has('category_ids');

        $roleValue = $this->validated('role');
        $role = $hasRole && $roleValue !== null ? UserRole::from((string) $roleValue) : null;

        return new UpdateUserData(
            name: $hasName ? (string) $this->validated('name') : null,
            email: $hasEmail ? (string) $this->validated('email') : null,
            role: $role,
            password: $hasPassword ? (string) $this->validated('password') : null,
            isActive: $hasIsActive ? (bool) $this->validated('is_active') : null,
            isAvailable: $hasIsAvailable ? (bool) $this->validated('is_available') : null,
            maxOpenTickets: $hasMaxOpenTickets && $this->validated('max_open_tickets') !== null ? (int) $this->validated('max_open_tickets') : null,
            categoryIds: $hasCategoryIds ? array_map('intval', (array) $this->validated('category_ids')) : null,
            hasName: $hasName,
            hasEmail: $hasEmail,
            hasRole: $hasRole,
            hasPassword: $hasPassword,
            hasIsActive: $hasIsActive,
            hasIsAvailable: $hasIsAvailable,
            hasMaxOpenTickets: $hasMaxOpenTickets,
            hasCategoryIds: $hasCategoryIds,
        );
    }
}
