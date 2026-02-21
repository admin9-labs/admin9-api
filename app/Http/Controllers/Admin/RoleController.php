<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Filters\RoleFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Services\RoleService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
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
            ->with('permissions:id,name')
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
            $request->validated('permission_ids') ?? [],
        );

        return $this->success($role);
    }

    /**
     * Get role detail.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id,name');

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
            $request->validated('permission_ids'),
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
