<?php

namespace App\Services;

use App\Data\ResetPasswordData;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public const DEFAULT_MESSAGE = 'If an active account exists for that email, a password reset link has been sent.';

    public function sendResetLink(string $email): string
    {
        $normalizedEmail = strtolower(trim($email));
        $user = User::where('email', $normalizedEmail)->first();

        if ($user !== null && $user->is_active) {
            Password::broker()->sendResetLink(['email' => $normalizedEmail]);
        }

        return self::DEFAULT_MESSAGE;
    }

    public function reset(ResetPasswordData $data): string
    {
        $normalizedEmail = strtolower(trim($data->email));
        $user = User::where('email', $normalizedEmail)->first();

        if ($user === null || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [trans(Password::INVALID_TOKEN)],
            ]);
        }

        $status = Password::broker()->reset(
            [
                'email' => $normalizedEmail,
                'token' => $data->token,
                'password' => $data->password,
                'password_confirmation' => $data->password,
            ],
            function (User $user, string $password): void {
                $user->password = $password;
                $user->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return trans($status);
    }
}
