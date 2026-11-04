<?php

namespace App\Http\Requests\Admin;

use App\Data\CreateUserData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): CreateUserData
    {
        return new CreateUserData(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            role: UserRole::from((string) $this->validated('role')),
            password: (string) $this->validated('password'),
            isActive: $this->has('is_active') ? (bool) $this->validated('is_active') : true,
        );
    }
}

