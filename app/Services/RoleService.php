<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Events\AuditRoleChanged;
use App\Exceptions\BusinessException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * @scaffold — Role business logic (super-admin protection, deletion guard).
 */
class RoleService
{
    /**
     * @throws BusinessException
     * @throws Throwable
     */
    public function createRole(string $name, array $permissionIds = []): Role
    {
        if ($name === RoleEnum::SuperAdmin->value) {
            throw new BusinessException('Cannot create role with reserved name: '.$name, 403);
        }

        return DB::transaction(function () use ($name, $permissionIds) {
            $role = Role::create(['name' => $name, 'guard_name' => 'api']);

            $permissions = $this->resolvePermissions($permissionIds);
            if ($permissions->isNotEmpty()) {
                $role->syncPermissions($permissions);
            }

            $role->load('permissions:id,name');

            DB::afterCommit(fn () => AuditRoleChanged::dispatch('created', auth()->id(), [
                'role_id' => $role->id,
                'name' => $name,
            ]));

            return $role;
        });
    }

    /**
     * @throws BusinessException
     * @throws Throwable
     */
    public function updateRole(Role $role, string $name, ?array $permissionIds = null): Role
    {
        if ($role->name === RoleEnum::SuperAdmin->value) {
            throw new BusinessException('Cannot modify super-admin role', 403);
        }

        if ($name === RoleEnum::SuperAdmin->value) {
            throw new BusinessException('Cannot use reserved role name: '.$name, 403);
        }

        return DB::transaction(function () use ($role, $name, $permissionIds) {
            $role->update(['name' => $name]);

            if ($permissionIds !== null) {
                $role->syncPermissions($this->resolvePermissions($permissionIds));
            }

            $role->load('permissions:id,name');

            DB::afterCommit(fn () => AuditRoleChanged::dispatch('updated', auth()->id(), [
                'role_id' => $role->id,
                'name' => $name,
            ]));

            return $role;
        });
    }

    /**
     * @throws BusinessException
     * @throws Throwable
     */
    public function deleteRole(Role $role): void
    {
        DB::transaction(function () use ($role) {
            $role = Role::lockForUpdate()->findOrFail($role->id);

            if ($role->name === RoleEnum::SuperAdmin->value) {
                throw new BusinessException('Cannot delete super-admin role', 403);
            }

            if ($role->users()->exists()) {
                throw new BusinessException('Cannot delete role that has users assigned. Remove users from this role first', 403);
            }

            $roleId = $role->id;
            $roleName = $role->name;

            $role->delete();

            DB::afterCommit(fn () => AuditRoleChanged::dispatch('deleted', auth()->id(), [
                'role_id' => $roleId,
                'name' => $roleName,
            ]));
        });
    }

    /**
     * @throws BusinessException
     */
    private function resolvePermissions(array $permissionIds): Collection
    {
        if (empty($permissionIds)) {
            return collect();
        }

        $permissionIds = array_values(array_unique($permissionIds));

        $permissions = Permission::whereIn('id', $permissionIds)
            ->where('guard_name', 'api')
            ->get();

        if ($permissions->count() !== count($permissionIds)) {
            throw new BusinessException('Invalid permission IDs', 422);
        }

        return $permissions;
    }
}
