<?php

namespace Tests\Unit\Services;

use App\Enums\Role as RoleEnum;
use App\Events\AuditRoleChanged;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RoleService::class);
    }

    // ---- createRole ----

    public function test_create_role_without_permissions(): void
    {
        Event::fake([AuditRoleChanged::class]);

        $role = $this->service->createRole('editor');

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'api']);
        $this->assertTrue($role->permissions->isEmpty());
        Event::assertDispatched(AuditRoleChanged::class, fn ($e) => $e->action === 'created');
    }

    public function test_create_role_with_permissions(): void
    {
        $perm = Permission::findOrCreate('users.read', 'api');

        $role = $this->service->createRole('viewer', [$perm->id]);

        $this->assertTrue($role->hasPermissionTo('users.read'));
    }

    public function test_create_role_rejects_super_admin_name(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot create role with reserved name');

        $this->service->createRole(RoleEnum::SuperAdmin->value);
    }

    public function test_create_role_rejects_invalid_permission_ids(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Invalid permission IDs');

        $this->service->createRole('editor', [99999]);
    }

    public function test_create_role_deduplicates_permission_ids(): void
    {
        $perm = Permission::findOrCreate('users.read', 'api');

        $role = $this->service->createRole('viewer', [$perm->id, $perm->id]);

        $this->assertCount(1, $role->permissions);
    }

    // ---- updateRole ----

    public function test_update_role_name(): void
    {
        Event::fake([AuditRoleChanged::class]);

        $role = Role::findOrCreate('editor', 'api');

        $updated = $this->service->updateRole($role, 'senior-editor');

        $this->assertEquals('senior-editor', $updated->name);
        Event::assertDispatched(AuditRoleChanged::class, fn ($e) => $e->action === 'updated');
    }

    public function test_update_role_permissions(): void
    {
        $role = Role::findOrCreate('editor', 'api');
        $perm = Permission::findOrCreate('users.read', 'api');

        $updated = $this->service->updateRole($role, 'editor', [$perm->id]);

        $this->assertTrue($updated->hasPermissionTo('users.read'));
    }

    public function test_update_role_skips_permissions_when_null(): void
    {
        $perm = Permission::findOrCreate('users.read', 'api');
        $role = Role::findOrCreate('editor', 'api');
        $role->syncPermissions([$perm]);

        $updated = $this->service->updateRole($role, 'editor', null);

        $this->assertTrue($updated->hasPermissionTo('users.read'));
    }

    public function test_update_super_admin_role_throws_exception(): void
    {
        $role = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot modify super-admin role');

        $this->service->updateRole($role, 'renamed');
    }

    public function test_update_role_rejects_renaming_to_super_admin(): void
    {
        $role = Role::findOrCreate('editor', 'api');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot use reserved role name');

        $this->service->updateRole($role, RoleEnum::SuperAdmin->value);
    }

    // ---- deleteRole ----

    public function test_delete_role(): void
    {
        Event::fake([AuditRoleChanged::class]);

        $role = Role::findOrCreate('disposable', 'api');
        $roleId = $role->id;

        $this->service->deleteRole($role);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
        Event::assertDispatched(AuditRoleChanged::class, fn ($e) => $e->action === 'deleted');
    }

    public function test_delete_super_admin_role_throws_exception(): void
    {
        $role = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot delete super-admin role');

        $this->service->deleteRole($role);
    }

    public function test_delete_role_with_users_throws_exception(): void
    {
        $role = Role::findOrCreate('occupied', 'api');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot delete role that has users assigned');

        $this->service->deleteRole($role);
    }
}
