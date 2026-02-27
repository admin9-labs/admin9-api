<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Exceptions\BusinessException;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
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
    public function createRole(string $name, array $menuIds = [], ?string $locale = null): Role
    {
        if ($name === RoleEnum::SuperAdmin->value) {
            throw new BusinessException('Cannot create role with reserved name: '.$name);
        }

        $this->validateMenuIds($menuIds);

        return DB::transaction(function () use ($name, $menuIds, $locale) {
            $role = new Role(['name' => $name, 'guard_name' => 'api', 'locale' => $locale]);
            $role->disableLogging();
            try {
                $role->save();
            } finally {
                $role->enableLogging();
            }

            if (! empty($menuIds)) {
                $role->menus()->sync($menuIds);
                $this->syncSpatiePermissions($role, $menuIds);
            }

            $role->load('menus');

            DB::afterCommit(fn () => activity('role')
                ->performedOn($role)
                ->causedBy(auth()->user())
                ->event('created_with_menus')
                ->withProperties([
                    'attributes' => ['menu_ids' => $menuIds],
                ])
                ->log('Role created with menus'));

            return $role;
        });
    }

    /**
     * @throws BusinessException
     * @throws Throwable
     */
    public function updateRole(Role $role, string $name, ?array $menuIds = null, ?string $locale = null): Role
    {
        if ($role->name === RoleEnum::SuperAdmin->value) {
            throw new BusinessException('Cannot modify super-admin role');
        }

        if ($name === RoleEnum::SuperAdmin->value && $role->name !== RoleEnum::SuperAdmin->value) {
            throw new BusinessException('Cannot use reserved role name: '.$name);
        }

        if ($menuIds !== null) {
            $this->validateMenuIds($menuIds);
        }

        return DB::transaction(function () use ($role, $name, $menuIds, $locale) {
            $oldMenuIds = $role->menus()->pluck('menus.id')->all();

            $role->update(['name' => $name, 'locale' => $locale]);

            if ($menuIds !== null) {
                $role->menus()->sync($menuIds);
                $this->syncSpatiePermissions($role, $menuIds);

                DB::afterCommit(fn () => activity('role')
                    ->performedOn($role)
                    ->causedBy(auth()->user())
                    ->event('menus_synced')
                    ->withProperties([
                        'old' => ['menu_ids' => $oldMenuIds],
                        'attributes' => ['menu_ids' => $menuIds],
                    ])
                    ->log('Role menus synced'));
            }

            $role->load('menus');

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
                throw new BusinessException('Cannot delete super-admin role');
            }

            if ($role->users()->exists()) {
                throw new BusinessException('Cannot delete role that has users assigned. Remove users from this role first');
            }

            $roleName = $role->name;
            $roleId = $role->id;

            // Clear relationships
            $role->menus()->detach();
            $role->syncPermissions([]);

            $role->disableLogging();
            try {
                $role->delete();
            } finally {
                $role->enableLogging();
            }

            DB::afterCommit(fn () => activity('role')
                ->causedBy(auth()->user())
                ->event('deleted')
                ->withProperties([
                    'old' => ['role_id' => $roleId, 'role_name' => $roleName],
                ])
                ->log('Role deleted'));
        });
    }

    /**
     * Sync Spatie permissions based on the provided menu IDs.
     */
    private function syncSpatiePermissions(Role $role, array $menuIds): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if (empty($menuIds)) {
            $role->syncPermissions([]);

            return;
        }

        $permissionsToSync = Menu::whereIn('id', $menuIds)
            ->where('type', Menu::TYPE_BUTTON)
            ->whereNotNull('permission')
            ->pluck('permission')
            ->toArray();

        $role->syncPermissions($permissionsToSync);
    }

    /**
     * Validate that all menu IDs exist.
     *
     * @throws BusinessException
     */
    private function validateMenuIds(array $menuIds): void
    {
        if (empty($menuIds)) {
            return;
        }

        $count = Menu::whereIn('id', $menuIds)->count();
        if ($count !== count(array_unique($menuIds))) {
            throw new BusinessException('Invalid menu IDs');
        }
    }
}
