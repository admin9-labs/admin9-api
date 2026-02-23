<?php

namespace App\Http\Controllers\System;

use App\Exceptions\BusinessException;
use App\Filters\RoleFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\RoleRequest;
use App\Models\Role;
use App\Services\RoleService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * @scaffold — Role CRUD + permission sync.
 */
#[Group('Roles', weight: 2)]
class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    /**
     * List roles.
     */
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', 'api')
            ->filter(RoleFilter::class)
            ->with('menus:id,name,locale')
            ->paginate();

        return $this->success($roles);
    }

    /**
     * Create role.
     *
     * @throws BusinessException
     * @throws Throwable
     */
    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->roleService->createRole(
            $request->validated('name'),
            $request->validated('menu_ids') ?? [],
            $request->validated('locale'),
        );

        return $this->success($role);
    }

    /**
     * Get role detail.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('menus:id,name,locale');

        return $this->success($role);
    }

    /**
     * Update role.
     *
     * @throws BusinessException
     * @throws Throwable
     */
    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->updateRole(
            $role,
            $request->validated('name'),
            $request->validated('menu_ids'),
            $request->validated('locale'),
        );

        return $this->success($role);
    }

    /**
     * Delete role.
     *
     * @throws BusinessException
     * @throws Throwable
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->deleteRole($role);

        return $this->success();
    }
}
