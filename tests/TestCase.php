<?php

namespace Tests;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

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

        // Seed permissions, menus, and roles
        $this->seed(\Database\Seeders\MenuSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Ensure we use the api guard
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

    protected function assertBusinessError(TestResponse $response, int $code = 1): TestResponse
    {
        return $response->assertOk()->assertJsonPath('success', false)->assertJsonPath('code', $code);
    }
}
