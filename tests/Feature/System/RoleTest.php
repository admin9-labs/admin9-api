<?php

namespace Tests\Feature\System;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_admin_can_list_roles(): void
    {
        $this->actingAsUser(['roles.read']);

        Role::findOrCreate('editor', 'api');
        Role::findOrCreate('viewer', 'api');

        $response = $this->getJson('/api/system/roles');

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination', 'page', 'page_size', 'total'],
            ]);
    }

    public function test_admin_can_create_role(): void
    {
        $this->actingAsUser(['roles.create']);

        $menu = Menu::factory()->create(['type' => Menu::TYPE_DIRECTORY]);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'new-role',
            'locale' => 'roles.newRole',
            'menu_ids' => [$menu->id],
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('roles', [
            'name' => 'new-role',
            'locale' => 'roles.newRole',
            'guard_name' => 'api',
        ]);
    }

    public function test_admin_can_update_role(): void
    {
        $this->actingAsUser(['roles.update']);

        $role = Role::findOrCreate('editor', 'api');

        $response = $this->putJson("/api/system/roles/{$role->id}", [
            'name' => 'updated-editor',
            'locale' => 'roles.updatedEditor',
            'menu_ids' => [],
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'updated-editor',
            'locale' => 'roles.updatedEditor',
        ]);
    }

    public function test_admin_can_delete_role(): void
    {
        $this->actingAsUser(['roles.delete']);

        $role = Role::findOrCreate('disposable', 'api');

        $response = $this->deleteJson("/api/system/roles/{$role->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_role_with_users(): void
    {
        $this->actingAsUser(['roles.delete']);

        $role = Role::findOrCreate('occupied-role', 'api');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        $response = $this->deleteJson("/api/system/roles/{$role->id}");

        $this->assertBusinessError($response, 403);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_admin_can_view_role(): void
    {
        $this->actingAsUser(['roles.read']);

        $role = Role::findOrCreate('editor', 'api');

        $response = $this->getJson("/api/system/roles/{$role->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.name', 'editor');
    }

    public function test_cannot_create_role_with_reserved_name(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'super-admin',
            'menu_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_can_create_role_with_admin_name(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'admin',
            'menu_ids' => [],
        ]);

        $this->assertBusinessSuccess($response);
    }

    public function test_can_modify_non_super_admin_system_role(): void
    {
        $this->actingAsSuperAdmin();

        $adminRole = Role::findOrCreate('admin', 'api');

        $response = $this->putJson("/api/system/roles/{$adminRole->id}", [
            'name' => 'renamed-admin',
            'menu_ids' => [],
        ]);

        $this->assertBusinessSuccess($response);
    }

    public function test_cannot_modify_super_admin_role(): void
    {
        $this->actingAsSuperAdmin();

        $superAdminRole = Role::findOrCreate('super-admin', 'api');

        $response = $this->putJson("/api/system/roles/{$superAdminRole->id}", [
            'name' => 'renamed',
            'menu_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_can_delete_non_super_admin_system_role(): void
    {
        $this->actingAsSuperAdmin();

        $adminRole = Role::findOrCreate('admin', 'api');

        $response = $this->deleteJson("/api/system/roles/{$adminRole->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseMissing('roles', ['id' => $adminRole->id]);
    }

    public function test_cannot_delete_super_admin_role(): void
    {
        $this->actingAsSuperAdmin();

        $superAdminRole = Role::findOrCreate('super-admin', 'api');

        $response = $this->deleteJson("/api/system/roles/{$superAdminRole->id}");

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_list_roles(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/system/roles');

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_create_role(): void
    {
        $this->actingAsUser([]);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'unauthorized-role',
            'menu_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_create_duplicate_role_name(): void
    {
        $this->actingAsUser(['roles.create']);

        Role::findOrCreate('existing-role', 'api');

        $response = $this->postJson('/api/system/roles', [
            'name' => 'existing-role',
            'menu_ids' => [],
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_cannot_rename_role_to_super_admin(): void
    {
        $this->actingAsUser(['roles.update']);

        $role = Role::findOrCreate('normal-role', 'api');

        $response = $this->putJson("/api/system/roles/{$role->id}", [
            'name' => 'super-admin',
            'menu_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_create_role_with_invalid_characters(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/system/roles', [
            'name' => '<script>alert(1)</script>',
            'menu_ids' => [],
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_user_without_permission_cannot_view_role(): void
    {
        $this->actingAsUser([]);
        $role = Role::findOrCreate('test-role', 'api');
        $response = $this->getJson("/api/system/roles/{$role->id}");
        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_update_role(): void
    {
        $this->actingAsUser([]);
        $role = Role::findOrCreate('test-role', 'api');
        $response = $this->putJson("/api/system/roles/{$role->id}", [
            'name' => 'updated',
            'menu_ids' => [],
        ]);
        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_delete_role(): void
    {
        $this->actingAsUser([]);
        $role = Role::findOrCreate('test-role', 'api');
        $response = $this->deleteJson("/api/system/roles/{$role->id}");
        $this->assertBusinessError($response, 403);
    }

    public function test_unauthenticated_user_cannot_access_admin_roles(): void
    {
        $response = $this->getJson('/api/system/roles');
        $this->assertBusinessError($response, -1);
    }

    public function test_cannot_create_role_with_xss_in_locale(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'xss-test-role',
            'locale' => '<script>alert(1)</script>',
            'menu_ids' => [],
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_cannot_create_role_with_nonexistent_permission_ids(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'test-role',
            'menu_ids' => [99999],
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_create_role_with_null_permission_ids_succeeds(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'null-perms-role',
            'menu_ids' => null,
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('roles', [
            'name' => 'null-perms-role',
            'guard_name' => 'api',
        ]);
    }
}
