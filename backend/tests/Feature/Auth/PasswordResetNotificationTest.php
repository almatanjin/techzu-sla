<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

it('sends reset password notification with configured frontend URL and email', function () {
    Notification::fake();

    $user = User::factory()->customer()->create([
        'email' => 'reset.user@example.test',
    ]);

    $user->sendPasswordResetNotification('sample-reset-token-xyz');

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use ($user) {
            $mail = $notification->toMail($user);
            expect($mail->actionUrl)->toStartWith('http://localhost:5173/reset-password?token=sample-reset-token-xyz')
                ->and($mail->actionUrl)->toContain('email=reset.user%40example.test');

            return true;
        }
    );
});

