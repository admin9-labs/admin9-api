<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_admin_can_list_roles(): void
    {
        $this->actingAsUser(['roles.read']);

        Role::findOrCreate('editor', 'api');
        Role::findOrCreate('viewer', 'api');

        $response = $this->getJson('/api/admin/roles');

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination', 'page', 'page_size', 'total'],
            ]);
    }

    public function test_admin_can_create_role(): void
    {
        $this->actingAsUser(['roles.create']);

        $permission = Permission::findOrCreate('users.read', 'api');

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'new-role',
            'permission_ids' => [$permission->id],
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('roles', [
            'name' => 'new-role',
            'guard_name' => 'api',
        ]);
    }

    public function test_admin_can_update_role(): void
    {
        $this->actingAsUser(['roles.update']);

        $role = Role::findOrCreate('editor', 'api');

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'name' => 'updated-editor',
            'permission_ids' => [],
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'updated-editor',
        ]);
    }

    public function test_admin_can_delete_role(): void
    {
        $this->actingAsUser(['roles.delete']);

        $role = Role::findOrCreate('disposable', 'api');

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_role_with_users(): void
    {
        $this->actingAsUser(['roles.delete']);

        $role = Role::findOrCreate('occupied-role', 'api');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $this->assertBusinessError($response, 403);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_admin_can_view_role(): void
    {
        $this->actingAsUser(['roles.read']);

        $role = Role::findOrCreate('editor', 'api');

        $response = $this->getJson("/api/admin/roles/{$role->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.name', 'editor');
    }

    public function test_cannot_create_role_with_reserved_name(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'super-admin',
            'permission_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_can_create_role_with_admin_name(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'admin',
            'permission_ids' => [],
        ]);

        $this->assertBusinessSuccess($response);
    }

    public function test_can_modify_non_super_admin_system_role(): void
    {
        $this->actingAsSuperAdmin();

        $adminRole = Role::findOrCreate('admin', 'api');

        $response = $this->putJson("/api/admin/roles/{$adminRole->id}", [
            'name' => 'renamed-admin',
            'permission_ids' => [],
        ]);

        $this->assertBusinessSuccess($response);
    }

    public function test_cannot_modify_super_admin_role(): void
    {
        $this->actingAsSuperAdmin();

        $superAdminRole = Role::findOrCreate('super-admin', 'api');

        $response = $this->putJson("/api/admin/roles/{$superAdminRole->id}", [
            'name' => 'renamed',
            'permission_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_can_delete_non_super_admin_system_role(): void
    {
        $this->actingAsSuperAdmin();

        $adminRole = Role::findOrCreate('admin', 'api');

        $response = $this->deleteJson("/api/admin/roles/{$adminRole->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseMissing('roles', ['id' => $adminRole->id]);
    }

    public function test_cannot_delete_super_admin_role(): void
    {
        $this->actingAsSuperAdmin();

        $superAdminRole = Role::findOrCreate('super-admin', 'api');

        $response = $this->deleteJson("/api/admin/roles/{$superAdminRole->id}");

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_list_roles(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/admin/roles');

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_create_role(): void
    {
        $this->actingAsUser([]);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'unauthorized-role',
            'permission_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_create_duplicate_role_name(): void
    {
        $this->actingAsUser(['roles.create']);

        Role::findOrCreate('existing-role', 'api');

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'existing-role',
            'permission_ids' => [],
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_cannot_rename_role_to_super_admin(): void
    {
        $this->actingAsUser(['roles.update']);

        $role = Role::findOrCreate('normal-role', 'api');

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'name' => 'super-admin',
            'permission_ids' => [],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_create_role_with_invalid_characters(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/admin/roles', [
            'name' => '<script>alert(1)</script>',
            'permission_ids' => [],
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_user_without_permission_cannot_view_role(): void
    {
        $this->actingAsUser([]);
        $role = Role::findOrCreate('test-role', 'api');
        $response = $this->getJson("/api/admin/roles/{$role->id}");
        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_update_role(): void
    {
        $this->actingAsUser([]);
        $role = Role::findOrCreate('test-role', 'api');
        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'name' => 'updated',
            'permission_ids' => [],
        ]);
        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_delete_role(): void
    {
        $this->actingAsUser([]);
        $role = Role::findOrCreate('test-role', 'api');
        $response = $this->deleteJson("/api/admin/roles/{$role->id}");
        $this->assertBusinessError($response, 403);
    }

    public function test_unauthenticated_user_cannot_access_admin_roles(): void
    {
        $response = $this->getJson('/api/admin/roles');
        $this->assertBusinessError($response, -1);
    }

    public function test_cannot_create_role_with_nonexistent_permission_ids(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'test-role',
            'permission_ids' => [99999],
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_create_role_with_null_permission_ids_succeeds(): void
    {
        $this->actingAsUser(['roles.create']);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'null-perms-role',
            'permission_ids' => null,
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('roles', [
            'name' => 'null-perms-role',
            'guard_name' => 'api',
        ]);
    }

    public function test_roles_list_respects_page_size_parameter(): void
    {
        $this->actingAsUser(['roles.read']);

        for ($i = 0; $i < 5; $i++) {
            Role::findOrCreate("role-{$i}", 'api');
        }

        $response = $this->getJson('/api/admin/roles?page_size=2');

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('meta.page_size', 2);

        $this->assertCount(2, $response->json('data'));
    }
}
