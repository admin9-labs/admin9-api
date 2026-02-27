<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // --- Dashboard Module ---
        $dashboardDir = Menu::updateOrCreate(
            ['name' => 'Dashboard'],
            [
                'type' => Menu::TYPE_DIRECTORY,
                'path' => '/dashboard',
                'locale' => 'menu.dashboard',
                'icon' => 'icon-dashboard',
                'sort' => 0,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'DashboardWorkplace'],
            [
                'parent_id' => $dashboardDir->id,
                'type' => Menu::TYPE_MENU,
                'path' => 'workplace',
                'component' => 'views/dashboard/workplace/index.vue',
                'locale' => 'menu.dashboard.workplace',
                'icon' => 'icon-desktop',
                'sort' => 0,
            ]
        );

        // --- User Module (Account) ---
        $userDir = Menu::updateOrCreate(
            ['name' => 'User'],
            [
                'type' => Menu::TYPE_DIRECTORY,
                'path' => '/user',
                'locale' => 'menu.user',
                'icon' => 'icon-user',
                'sort' => 1,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'UserInfo'],
            [
                'parent_id' => $userDir->id,
                'type' => Menu::TYPE_MENU,
                'path' => 'info',
                'component' => 'views/user/info/index.vue',
                'locale' => 'menu.user.info',
                'icon' => 'icon-idcard',
                'sort' => 1,
            ]
        );

        // --- System Module ---
        $systemDir = Menu::updateOrCreate(
            ['name' => 'System'],
            [
                'type' => Menu::TYPE_DIRECTORY,
                'path' => '/system',
                'locale' => 'menu.system',
                'icon' => 'icon-settings',
                'sort' => 2,
            ]
        );

        // User Management Menu
        $systemUserMenu = Menu::updateOrCreate(
            ['name' => 'SystemUser'],
            [
                'parent_id' => $systemDir->id,
                'type' => Menu::TYPE_MENU,
                'path' => 'user',
                'component' => 'views/system/user/index.vue',
                'locale' => 'menu.system.user',
                'icon' => 'icon-user',
                'sort' => 3,
            ]
        );

        Menu::updateOrCreate(['name' => 'users.read'], ['parent_id' => $systemUserMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'users.read', 'locale' => 'system.permissions.read']);
        Menu::updateOrCreate(['name' => 'users.update'], ['parent_id' => $systemUserMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'users.update', 'locale' => 'system.permissions.update']);
        Menu::updateOrCreate(['name' => 'users.assignRoles'], ['parent_id' => $systemUserMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'users.assignRoles', 'locale' => 'system.permissions.assignRoles']);
        Menu::updateOrCreate(['name' => 'users.toggleStatus'], ['parent_id' => $systemUserMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'users.toggleStatus', 'locale' => 'system.permissions.toggleStatus']);
        Menu::updateOrCreate(['name' => 'users.resetPassword'], ['parent_id' => $systemUserMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'users.resetPassword', 'locale' => 'system.permissions.resetPassword']);

        // Role Management Menu
        $systemRoleMenu = Menu::updateOrCreate(
            ['name' => 'SystemRole'],
            [
                'parent_id' => $systemDir->id,
                'type' => Menu::TYPE_MENU,
                'path' => 'role',
                'component' => 'views/system/role/index.vue',
                'locale' => 'menu.system.role',
                'icon' => 'icon-user-group',
                'sort' => 2,
            ]
        );

        Menu::updateOrCreate(['name' => 'roles.read'], ['parent_id' => $systemRoleMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'roles.read', 'locale' => 'system.permissions.read']);
        Menu::updateOrCreate(['name' => 'roles.create'], ['parent_id' => $systemRoleMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'roles.create', 'locale' => 'system.permissions.create']);
        Menu::updateOrCreate(['name' => 'roles.update'], ['parent_id' => $systemRoleMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'roles.update', 'locale' => 'system.permissions.update']);
        Menu::updateOrCreate(['name' => 'roles.delete'], ['parent_id' => $systemRoleMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'roles.delete', 'locale' => 'system.permissions.delete']);

        // Menu Management Menu
        $systemMenuMenu = Menu::updateOrCreate(
            ['name' => 'SystemMenu'],
            [
                'parent_id' => $systemDir->id,
                'type' => Menu::TYPE_MENU,
                'path' => 'menu',
                'component' => 'views/system/menu/index.vue',
                'locale' => 'menu.system.menu',
                'icon' => 'icon-menu',
                'sort' => 1,
            ]
        );

        Menu::updateOrCreate(['name' => 'menus.read'], ['parent_id' => $systemMenuMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'menus.read', 'locale' => 'system.permissions.read']);
        Menu::updateOrCreate(['name' => 'menus.create'], ['parent_id' => $systemMenuMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'menus.create', 'locale' => 'system.permissions.create']);
        Menu::updateOrCreate(['name' => 'menus.update'], ['parent_id' => $systemMenuMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'menus.update', 'locale' => 'system.permissions.update']);
        Menu::updateOrCreate(['name' => 'menus.delete'], ['parent_id' => $systemMenuMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'menus.delete', 'locale' => 'system.permissions.delete']);

        // Dictionary Management Menu (under System, final level)
        $systemDictMenu = Menu::updateOrCreate(
            ['name' => 'SystemDict'],
            [
                'parent_id' => $systemDir->id,
                'type' => Menu::TYPE_MENU,
                'path' => 'dict',
                'component' => 'views/system/dict/index.vue',
                'locale' => 'menu.system.dict',
                'icon' => 'icon-book',
                'sort' => 4,
            ]
        );

        Menu::updateOrCreate(['name' => 'dictTypes.read'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictTypes.read', 'locale' => 'system.permissions.read']);
        Menu::updateOrCreate(['name' => 'dictTypes.create'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictTypes.create', 'locale' => 'system.permissions.create']);
        Menu::updateOrCreate(['name' => 'dictTypes.update'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictTypes.update', 'locale' => 'system.permissions.update']);
        Menu::updateOrCreate(['name' => 'dictTypes.delete'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictTypes.delete', 'locale' => 'system.permissions.delete']);
        Menu::updateOrCreate(['name' => 'dictItems.read'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictItems.read', 'locale' => 'system.permissions.read']);
        Menu::updateOrCreate(['name' => 'dictItems.create'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictItems.create', 'locale' => 'system.permissions.create']);
        Menu::updateOrCreate(['name' => 'dictItems.update'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictItems.update', 'locale' => 'system.permissions.update']);
        Menu::updateOrCreate(['name' => 'dictItems.delete'], ['parent_id' => $systemDictMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'dictItems.delete', 'locale' => 'system.permissions.delete']);

        // Log Management Menu (under System, final level)
        $systemLogMenu = Menu::updateOrCreate(
            ['name' => 'SystemLog'],
            [
                'parent_id' => $systemDir->id,
                'type' => Menu::TYPE_MENU,
                'path' => 'log',
                'component' => 'views/system/log/index.vue',
                'locale' => 'menu.system.log',
                'icon' => 'icon-file',
                'sort' => 5,
            ]
        );

        Menu::updateOrCreate(['name' => 'auditLogs.read'], ['parent_id' => $systemLogMenu->id, 'type' => Menu::TYPE_BUTTON, 'permission' => 'auditLogs.read', 'locale' => 'system.permissions.read']);
    }
}
