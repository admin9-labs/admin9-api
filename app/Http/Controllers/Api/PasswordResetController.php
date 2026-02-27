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
    public function reset(ResetPasswordRequest $request, UserService $service): JsonResponse
    {
        $service->completePasswordReset(
            $request->validated()
        );

        return $this->success(message: 'Password has been reset successfully');
    }
}
