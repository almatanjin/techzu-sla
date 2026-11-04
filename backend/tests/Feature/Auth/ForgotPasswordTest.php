<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\PasswordResetService;
use Illuminate\Support\Facades\Notification;

it('returns generic message and notifies active known user', function () {
    Notification::fake();

    $user = User::factory()->customer()->create([
        'email' => 'active@example.test',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'ACTIVE@EXAMPLE.TEST',
    ]);

    $response->assertOk()
        ->assertExactJson([
            'message' => PasswordResetService::DEFAULT_MESSAGE,
        ]);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('returns identical generic message for unknown email without sending notification', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'unknown@example.test',
    ]);

    $response->assertOk()
        ->assertExactJson([
            'message' => PasswordResetService::DEFAULT_MESSAGE,
        ]);

    Notification::assertNothingSent();
});

it('returns identical generic message for deactivated user without sending notification', function () {
    Notification::fake();

    $user = User::factory()->inactive()->create([
        'email' => 'deactivated@example.test',
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'deactivated@example.test',
    ]);

    $response->assertOk()
        ->assertExactJson([
            'message' => PasswordResetService::DEFAULT_MESSAGE,
        ]);

    Notification::assertNothingSent();
});

