<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.read',
            'users.update',
            'users.toggleStatus',
            'users.resetPassword',
            'roles.read',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.read',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }
    }
}
