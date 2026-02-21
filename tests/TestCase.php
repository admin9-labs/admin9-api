<?php

namespace Tests;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withHeaders(['Accept' => 'application/json']);
    }

    protected function actingAsUser(array $permissions = [], string $roleName = 'test-role'): User
    {
        $user = User::factory()->create(['is_active' => true]);

        $role = Role::findOrCreate($roleName, 'api');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $role->syncPermissions($permissions);
        $user->assignRole($role);

        $this->actingAs($user, 'api');

        return $user;
    }

    protected function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);

        // 运行 seeder 创建权限和角色
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // 确保使用 api guard
        $superAdminRole = Role::where('name', RoleEnum::SuperAdmin->value)
            ->where('guard_name', 'api')
            ->first();

        $user->assignRole($superAdminRole);

        $this->actingAs($user, 'api');

        return $user;
    }

    protected function assertBusinessSuccess(TestResponse $response): TestResponse
    {
        return $response->assertOk()->assertJsonPath('success', true);
    }

    protected function assertBusinessError(TestResponse $response, int $code = 400): TestResponse
    {
        return $response->assertOk()->assertJsonPath('success', false)->assertJsonPath('code', $code);
    }
}
