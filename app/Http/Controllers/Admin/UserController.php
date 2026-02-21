<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Filters\UserFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * @scaffold — User CRUD + role sync + status toggle + password reset.
 */
#[Group('Users', weight: 1)]
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * List users.
     */
    public function index(): JsonResponse
    {
        $users = User::filter(UserFilter::class)
            ->with('roles:id,name')
            ->paginate();

        return $this->success($users);
    }

    /**
     * Get user detail.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles:id,name', 'permissions:id,name']);

        return $this->success($user);
    }

    /**
     * Update user.
     *
     * @throws BusinessException
     */
    public function update(UserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->updateUser(
            $user,
            $request->validated(),
            $request->has('role_ids') ? $request->validated('role_ids', []) : null,
        );

        return $this->success($user);
    }

    /**
     * Toggle user status.
     *
     * @throws BusinessException
     */
    public function toggleStatus(UserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->toggleStatus($user, $request->validated('is_active'), auth()->id());

        return $this->success($user);
    }

    /**
     * Reset user password.
     *
     * @throws BusinessException
     */
    public function resetPassword(User $user): JsonResponse
    {
        $this->userService->resetPassword($user);

        return $this->success(['message' => 'Password has been reset. Please use CLI command to retrieve the new password.']);
    }
}
