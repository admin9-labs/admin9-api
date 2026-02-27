<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Services\UserService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Password Reset')]
class PasswordResetController extends Controller
{
    /**
     * Send password reset link.
     */
    public function forgot(ResetPasswordRequest $request, UserService $service): JsonResponse
    {
        $service->sendResetLink($request->validated('email'));

        return $this->success(message: 'Reset link has been sent to your email');
    }

    /**
     * Reset password with token.
     */
    public function reset(ResetPasswordRequest $request, UserService $service): JsonResponse
    {
        $service->completePasswordReset(
            $request->validated()
        );

        return $this->success(message: 'Password has been reset successfully');
    }
}
