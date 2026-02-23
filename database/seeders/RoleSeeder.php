<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache to ensure syncPermissions can find permissions created by Menu::saved event
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Super Admin - Has all menus / permissions
        $superAdmin = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');
        $superAdmin->update(['locale' => RoleEnum::SuperAdmin->locale()]);
        $allMenuIds = Menu::pluck('id')->toArray();
        $superAdmin->menus()->sync($allMenuIds);
        
        $superAdminPermissions = Menu::whereIn('id', $allMenuIds)->where('type', Menu::TYPE_BUTTON)->whereNotNull('permission')->pluck('permission')->toArray();
        $superAdmin->syncPermissions($superAdminPermissions);

        // Admin - Standard permissions
        $admin = Role::findOrCreate(RoleEnum::Admin->value, 'api');
        $admin->update(['locale' => RoleEnum::Admin->locale()]);
        
        $adminMenuIds = Menu::whereIn('name', [
            'Dashboard', 'DashboardWorkplace',
            'User', 'UserInfo', 'UserAuthentication',
            'System', 'SystemUser', 'SystemRole', 'SystemMenu', 'SystemDict', 'SystemLog',
            'users.read', 'users.update', 'users.toggleStatus', 'users.resetPassword',
            'roles.read', 'menus.read', 'dictTypes.read', 'dictItems.read', 'auditLogs.read',
        ])->pluck('id')->toArray();

        $admin->menus()->sync($adminMenuIds);
        
        $adminPermissions = Menu::whereIn('id', $adminMenuIds)->where('type', Menu::TYPE_BUTTON)->whereNotNull('permission')->pluck('permission')->toArray();
        $admin->syncPermissions($adminPermissions);

        // User
        $userRole = Role::findOrCreate(RoleEnum::User->value, 'api');
        $userRole->update(['locale' => RoleEnum::User->locale()]);
        $userMenuIds = Menu::whereIn('name', [
            'Dashboard', 'DashboardWorkplace',
            'User', 'UserInfo', 'UserAuthentication'
        ])->pluck('id')->toArray();
        $userRole->menus()->sync($userMenuIds);
        $userRole->syncPermissions([]);
    }
}
