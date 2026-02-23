<?php

namespace Tests\Unit\Services;

use App\Enums\Role as RoleEnum;
use App\Exceptions\BusinessException;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
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
        $role = $this->service->createRole('editor', [], 'roles.editor');

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'api', 'locale' => 'roles.editor']);
        $this->assertTrue($role->permissions->isEmpty());
    }

    public function test_create_role_with_menus(): void
    {
        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'permission' => 'users.read',
        ]);

        $role = $this->service->createRole('viewer', [$menu->id]);

        $this->assertTrue($role->hasPermissionTo('users.read'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'role', 'event' => 'created_with_menus']);
    }

    public function test_create_role_rejects_super_admin_name(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot create role with reserved name');

        $this->service->createRole(RoleEnum::SuperAdmin->value);
    }

    public function test_create_role_rejects_invalid_menu_ids(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Invalid menu IDs');

        $this->service->createRole('editor', [99999]);
    }

    public function test_create_role_deduplicates_menu_ids(): void
    {
        $menu = Menu::factory()->create();

        $role = $this->service->createRole('viewer', [$menu->id, $menu->id]);

        $this->assertCount(1, $role->menus);
    }

    // ---- updateRole ----

    public function test_update_role_name(): void
    {
        $role = Role::findOrCreate('editor', 'api');

        $updated = $this->service->updateRole($role, 'senior-editor', null, 'roles.seniorEditor');

        $this->assertEquals('senior-editor', $updated->name);
        $this->assertEquals('roles.seniorEditor', $updated->locale);
    }

    public function test_update_role_menus(): void
    {
        $role = Role::findOrCreate('editor', 'api');
        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'permission' => 'users.read',
        ]);

        $updated = $this->service->updateRole($role, 'editor', [$menu->id]);

        $this->assertTrue($updated->hasPermissionTo('users.read'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'role', 'event' => 'menus_synced']);
    }

    public function test_update_role_skips_menus_when_null(): void
    {
        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'permission' => 'users.read',
        ]);
        $role = Role::findOrCreate('editor', 'api');
        $role->menus()->sync([$menu->id]);
        $role->syncPermissions(['users.read']);

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
        $role = Role::findOrCreate('disposable', 'api');
        $roleId = $role->id;

        $this->service->deleteRole($role);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'role', 'event' => 'deleted']);
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

    public function test_create_role_only_produces_manual_audit_log(): void
    {
        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'permission' => 'audit.read',
        ]);

        $role = $this->service->createRole('audit-test', [$menu->id]);

        $logs = \Spatie\Activitylog\Models\Activity::where('log_name', 'role')
            ->where('subject_id', $role->id)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('created_with_menus', $logs->first()->event);
    }

    public function test_delete_role_only_produces_manual_audit_log(): void
    {
        $role = Role::findOrCreate('delete-audit', 'api');
        $roleId = $role->id;

        $this->service->deleteRole($role);

        $logs = \Spatie\Activitylog\Models\Activity::where('log_name', 'role')
            ->where('properties->role_id', $roleId)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('deleted', $logs->first()->event);
    }
}
