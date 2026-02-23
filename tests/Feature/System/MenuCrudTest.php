<?php

namespace Tests\Feature\System;

use App\Models\Menu;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MenuCrudTest extends TestCase
{
    public function test_can_list_menus(): void
    {
        $this->actingAsUser(['menus.read']);

        Menu::factory()->count(3)->create();

        $response = $this->getJson('/api/system/menus');

        $this->assertBusinessSuccess($response);
    }

    public function test_can_create_menu(): void
    {
        $this->actingAsUser(['menus.create']);

        $response = $this->postJson('/api/system/menus', [
            'name' => 'TestMenu',
            'locale' => 'menu.test',
            'type' => Menu::TYPE_MENU,
            'path' => '/test',
            'component' => 'views/test/index.vue',
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('menus', [
            'name' => 'TestMenu',
            'path' => '/test',
        ]);
    }

    public function test_can_show_menu(): void
    {
        $this->actingAsUser(['menus.read']);

        $menu = Menu::factory()->create();

        $response = $this->getJson("/api/system/menus/{$menu->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.id', $menu->id);
    }

    public function test_can_update_menu(): void
    {
        $this->actingAsUser(['menus.update']);

        $menu = Menu::factory()->create(['name' => 'OldName', 'locale' => 'menu.old']);

        $response = $this->putJson("/api/system/menus/{$menu->id}", [
            'name' => 'NewName',
            'locale' => 'menu.new',
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'NewName',
        ]);
    }

    public function test_can_delete_menu(): void
    {
        $this->actingAsUser(['menus.delete']);

        $menu = Menu::factory()->create();

        $response = $this->deleteJson("/api/system/menus/{$menu->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_cannot_delete_menu_with_children(): void
    {
        $this->actingAsUser(['menus.delete']);

        $parent = Menu::factory()->create();
        Menu::factory()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/system/menus/{$parent->id}");

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_set_menu_as_own_parent(): void
    {
        $this->actingAsUser(['menus.update']);

        $menu = Menu::factory()->create(['name' => 'SelfRef', 'locale' => 'menu.selfref']);

        $response = $this->putJson("/api/system/menus/{$menu->id}", [
            'name' => 'SelfRef',
            'locale' => 'menu.selfref',
            'parent_id' => $menu->id,
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_cannot_create_duplicate_name(): void
    {
        $this->actingAsUser(['menus.create']);

        Menu::factory()->create(['name' => 'Unique']);

        $response = $this->postJson('/api/system/menus', [
            'name' => 'Unique',
            'locale' => 'menu.unique',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_xss_rejected_in_name(): void
    {
        $this->actingAsUser(['menus.create']);

        $response = $this->postJson('/api/system/menus', [
            'name' => '<script>alert(1)</script>',
            'locale' => 'menu.xss',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_user_without_permission_cannot_list_menus(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/system/menus');

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_create_menu(): void
    {
        $this->actingAsUser([]);

        $response = $this->postJson('/api/system/menus', [
            'name' => 'Test',
            'locale' => 'menu.test',
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_update_menu(): void
    {
        $this->actingAsUser([]);

        $menu = Menu::factory()->create();

        $response = $this->putJson("/api/system/menus/{$menu->id}", [
            'name' => 'Updated',
            'locale' => 'menu.updated',
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_delete_menu(): void
    {
        $this->actingAsUser([]);

        $menu = Menu::factory()->create();

        $response = $this->deleteJson("/api/system/menus/{$menu->id}");

        $this->assertBusinessError($response, 403);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/system/menus');

        $this->assertBusinessError($response, -1);
    }

    public function test_updating_button_permission_migrates_role_association(): void
    {
        $this->actingAsUser(['menus.update']);

        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'name' => 'ReadBtn',
            'permission' => 'posts.read',
        ]);

        $role = Role::findOrCreate('editor', 'api');
        $role->menus()->sync([$menu->id]);
        $role->syncPermissions(['posts.read']);

        $response = $this->putJson("/api/system/menus/{$menu->id}", [
            'name' => 'ReadBtn',
            'locale' => 'menu.readBtn',
            'type' => Menu::TYPE_BUTTON,
            'permission' => 'posts.view',
        ]);

        $this->assertBusinessSuccess($response);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('posts.view'));
        $this->assertNull(Permission::where('name', 'posts.read')->where('guard_name', 'api')->first());
    }

    public function test_deleting_button_menu_removes_orphaned_permission(): void
    {
        $this->actingAsUser(['menus.delete']);

        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'name' => 'OrphanBtn',
            'permission' => 'orphan.perm',
        ]);

        $this->assertNotNull(Permission::where('name', 'orphan.perm')->where('guard_name', 'api')->first());

        $response = $this->deleteJson("/api/system/menus/{$menu->id}");

        $this->assertBusinessSuccess($response);
        $this->assertNull(Permission::where('name', 'orphan.perm')->where('guard_name', 'api')->first());
    }

    public function test_deleting_button_menu_detaches_permission_from_roles(): void
    {
        $this->actingAsUser(['menus.delete']);

        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'name' => 'RoleBtn',
            'permission' => 'rolebtn.perm',
        ]);

        $role = Role::findOrCreate('btn-role', 'api');
        $role->givePermissionTo('rolebtn.perm');
        $this->assertTrue($role->hasPermissionTo('rolebtn.perm'));

        $response = $this->deleteJson("/api/system/menus/{$menu->id}");

        $this->assertBusinessSuccess($response);

        $role->refresh();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertNull(Permission::where('name', 'rolebtn.perm')->where('guard_name', 'api')->first());
    }

    public function test_changing_type_from_button_to_menu_clears_permission(): void
    {
        $this->actingAsUser(['menus.update']);

        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'name' => 'TypeChangeBtn',
            'permission' => 'typechange.perm',
        ]);

        $response = $this->putJson("/api/system/menus/{$menu->id}", [
            'name' => 'TypeChangeBtn',
            'locale' => 'menu.typeChange',
            'type' => Menu::TYPE_MENU,
            'path' => '/type-change',
            'component' => 'views/type-change/index.vue',
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertNull(Permission::where('name', 'typechange.perm')->where('guard_name', 'api')->first());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'menu',
            'event' => 'permission_cleared',
        ]);
    }

    public function test_changing_type_from_button_to_directory_clears_permission(): void
    {
        $this->actingAsUser(['menus.update']);

        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'name' => 'DirChangeBtn',
            'permission' => 'dirchange.perm',
        ]);

        $response = $this->putJson("/api/system/menus/{$menu->id}", [
            'name' => 'DirChangeBtn',
            'locale' => 'menu.dirChange',
            'type' => Menu::TYPE_DIRECTORY,
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertNull(Permission::where('name', 'dirchange.perm')->where('guard_name', 'api')->first());
    }

    public function test_clearing_button_permission_logs_audit_event(): void
    {
        $this->actingAsUser(['menus.update']);

        $menu = Menu::factory()->create([
            'type' => Menu::TYPE_BUTTON,
            'name' => 'ClearPermBtn',
            'permission' => 'clearme.perm',
        ]);

        $response = $this->putJson("/api/system/menus/{$menu->id}", [
            'name' => 'ClearPermBtn',
            'locale' => 'menu.clearPerm',
            'type' => Menu::TYPE_MENU,
            'path' => '/clear-perm',
            'component' => 'views/clear-perm/index.vue',
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'menu',
            'event' => 'permission_cleared',
            'description' => 'permission_cleared',
        ]);
    }
}
