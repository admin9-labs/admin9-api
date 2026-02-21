<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin - 拥有所有权限
        $superAdmin = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');
        $superAdmin->syncPermissions(Permission::where('guard_name', 'api')->get());

        // Admin
        $admin = Role::findOrCreate(RoleEnum::Admin->value, 'api');
        $admin->syncPermissions([
            'users.read', 'users.update', 'users.toggleStatus', 'users.resetPassword',
            'roles.read',
            'permissions.read',
        ]);

        // User
        $user = Role::findOrCreate(RoleEnum::User->value, 'api');
        $user->syncPermissions([]);
    }
}
