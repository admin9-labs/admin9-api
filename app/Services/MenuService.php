<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Exceptions\BusinessException;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class MenuService
{
    /**
     * Get full menu tree for admin management.
     */
    public function getTree(): array
    {
        return Menu::treeOf(function ($query) {
            $query->where('parent_id', 0);
        })->orderBy('sort')->orderBy('id')->get()->toTree()->toArray();
    }

    /**
     * Get filtered menu tree for a specific user based on their roles.
     */
    public function getMenuTreeForUser(User $user): array
    {
        $userRoleIds = $user->roles()->pluck('id')->toArray();
        $isSuperAdmin = $user->hasRole(RoleEnum::SuperAdmin->value);

        $query = Menu::where('is_active', true)
            ->whereIn('type', [Menu::TYPE_DIRECTORY, Menu::TYPE_MENU])
            ->orderBy('sort');

        if (! $isSuperAdmin) {
            $query->with('roles:id');
        }

        $allMenus = $query->get();

        return $this->buildUserMenuTree($allMenus, 0, $userRoleIds, $isSuperAdmin);
    }

    private function buildUserMenuTree(\Illuminate\Database\Eloquent\Collection $allMenus, int $parentId, array $userRoleIds, bool $isSuperAdmin): array
    {
        return $allMenus->where('parent_id', $parentId)
            ->filter(function (Menu $menu) use ($userRoleIds, $isSuperAdmin) {
                if ($isSuperAdmin) {
                    return true;
                }

                return $menu->roles->pluck('id')->intersect($userRoleIds)->isNotEmpty();
            })
            ->map(function (Menu $menu) use ($allMenus, $userRoleIds, $isSuperAdmin) {
                $children = $this->buildUserMenuTree($allMenus, $menu->id, $userRoleIds, $isSuperAdmin);

                return [
                    'name' => $menu->name,
                    'path' => $menu->path,
                    'component' => $menu->type === Menu::TYPE_MENU ? $menu->component : null,
                    'meta' => [
                        'locale' => $menu->locale,
                        'icon' => $menu->icon,
                        'requiresAuth' => true,
                        'hideInMenu' => $menu->is_hidden,
                        'order' => $menu->sort,
                    ],
                    'children' => $children,
                ];
            })->values()->toArray();
    }

    /**
     * Create a menu item.
     *
     * @throws BusinessException
     */
    public function createMenu(array $data): Menu
    {
        return DB::transaction(function () use ($data) {
            $menu = Menu::create($data);

            $menu->load('children');

            return $menu;
        });
    }

    /**
     * Update a menu item.
     *
     * @throws BusinessException
     */
    public function updateMenu(Menu $menu, array $data): Menu
    {
        if (isset($data['parent_id']) && $data['parent_id'] !== 0) {
            if ($data['parent_id'] === $menu->id) {
                throw new BusinessException('Cannot set menu as its own parent', 422);
            }

            if ($menu->descendants()->where('id', $data['parent_id'])->exists()) {
                throw new BusinessException('Cannot set a descendant as parent', 422);
            }
        }

        return DB::transaction(function () use ($menu, $data) {
            $oldPermission = $menu->permission;
            $oldType = $menu->type;

            $menu->update($data);

            if ($oldPermission && $oldPermission !== $menu->permission) {
                $this->migratePermission($oldPermission, $menu->permission);
            } elseif ($oldType === Menu::TYPE_BUTTON && $menu->type !== Menu::TYPE_BUTTON && $oldPermission) {
                $this->migratePermission($oldPermission, null);
            }

            $menu->load('children');

            return $menu;
        });
    }

    /**
     * Delete a menu item.
     *
     * @throws BusinessException
     */
    public function deleteMenu(Menu $menu): void
    {
        if ($menu->children()->exists()) {
            throw new BusinessException('Cannot delete menu that has children. Remove children first', 403);
        }

        DB::transaction(function () use ($menu) {
            if ($menu->type === Menu::TYPE_BUTTON && $menu->permission) {
                $this->removeOrphanedPermission($menu->permission, $menu->id);
            }

            $menu->delete();
        });
    }

    /**
     * Migrate roles from old permission to new permission when a menu's permission field changes.
     */
    private function migratePermission(string $oldPermission, ?string $newPermission): void
    {
        $otherMenuUsesOld = Menu::where('permission', $oldPermission)
            ->where('type', Menu::TYPE_BUTTON)
            ->exists();

        if ($otherMenuUsesOld) {
            return;
        }

        $oldPerm = Permission::where('name', $oldPermission)->where('guard_name', 'api')->first();
        if (! $oldPerm) {
            return;
        }

        if ($newPermission) {
            $newPerm = Permission::findOrCreate($newPermission, 'api');
            $rolesWithOld = $oldPerm->roles()->get();

            foreach ($rolesWithOld as $role) {
                if (! $role->hasPermissionTo($newPerm)) {
                    $role->givePermissionTo($newPerm);
                }
            }
        }

        if (! $newPermission) {
            activity('menu')
                ->causedBy(auth()->user())
                ->event('permission_cleared')
                ->withProperties(array_filter([
                    'old_permission' => $oldPermission,
                    'ip' => Context::get('ip'),
                ], fn ($v) => $v !== null))
                ->log('permission_cleared');
        }

        $oldPerm->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Remove a permission if no other menu references it.
     */
    private function removeOrphanedPermission(string $permissionName, int $excludeMenuId): void
    {
        $otherMenuUses = Menu::where('permission', $permissionName)
            ->where('type', Menu::TYPE_BUTTON)
            ->where('id', '!=', $excludeMenuId)
            ->exists();

        if ($otherMenuUses) {
            return;
        }

        $perm = Permission::where('name', $permissionName)->where('guard_name', 'api')->first();
        if (! $perm) {
            return;
        }
        $perm->roles()->detach();
        $perm->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
