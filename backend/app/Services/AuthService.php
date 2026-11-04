<?php

namespace App\Services;

use App\Data\RegisterData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{token: string, token_type: string, expires_at: string, user: User}
     */
    public function register(RegisterData $data): array
    {
        $user = new User([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);
        $user->role = UserRole::Customer;
        $user->is_active = true;
        $user->save();

        $expirationMinutes = (int) config('sanctum.expiration', 10080);
        $expiresAt = now()->addMinutes($expirationMinutes);

        $token = $user->createToken('api', ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
            'user' => $user,
        ];
    }

    /**
     * @return array{token: string, token_type: string, expires_at: string, user: User}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $expirationMinutes = (int) config('sanctum.expiration', 10080);
        $expiresAt = now()->addMinutes($expirationMinutes);

        $token = $user->createToken('api', ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
            'user' => $user,
        ];
    }

    public function logout(mixed $token): void
    {
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }
    }
}
