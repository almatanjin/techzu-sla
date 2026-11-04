<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        $message = $this->passwordResetService->sendResetLink(
            (string) $request->validated('email'),
        );

        return response()->json([
            'message' => $message,
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $message = $this->passwordResetService->reset($request->toData());

        return response()->json([
            'message' => $message,
        ]);
    }
}
