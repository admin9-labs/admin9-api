<?php

namespace Tests\Feature\Events;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    // ---- User events ----

    public function test_syncing_user_roles_persists_activity_log(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'api']);

        $this->putJson("/api/system/users/{$user->id}/assign-roles", [
            'role_ids' => [$role->id],
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'roles_synced',
            'subject_id' => $user->id,
        ]);
    }

    public function test_resetting_password_persists_activity_log(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->postJson("/api/system/users/{$user->id}/reset-password");

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'password_reset_requested',
            'subject_id' => $user->id,
        ]);
    }

    // ---- Login events ----

    public function test_successful_login_persists_activity_log(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        User::factory()->create(['email' => 'test@example.com', 'password' => 'password', 'is_active' => true]);

        $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'password']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'login_success',
        ]);
    }

    public function test_failed_login_persists_activity_log(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->postJson('/api/auth/login', ['email' => 'wrong@example.com', 'password' => 'wrongpassword']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'login_failed',
        ]);
    }

    public function test_disabled_user_login_persists_activity_log(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        User::factory()->create(['email' => 'disabled@example.com', 'password' => 'password', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => 'disabled@example.com', 'password' => 'password']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'login_blocked_inactive',
        ]);
    }

    // ---- Role events ----

    public function test_creating_role_with_menus_persists_activity_log(): void
    {
        $this->actingAsSuperAdmin();
        $menu = Menu::factory()->create(['type' => Menu::TYPE_BUTTON, 'permission' => 'posts.read']);

        $this->postJson('/api/system/roles', [
            'name' => 'reviewer',
            'menu_ids' => [$menu->id],
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'event' => 'created_with_menus',
        ]);
    }

    public function test_updating_role_menus_persists_activity_log(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::create(['name' => 'editor', 'guard_name' => 'api']);
        $menu = Menu::factory()->create(['type' => Menu::TYPE_BUTTON, 'permission' => 'posts.update']);

        $this->putJson("/api/system/roles/{$role->id}", [
            'name' => 'editor',
            'menu_ids' => [$menu->id],
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'event' => 'menus_synced',
        ]);
    }

    public function test_deleting_role_persists_activity_log(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::create(['name' => 'disposable', 'guard_name' => 'api']);

        $this->deleteJson("/api/system/roles/{$role->id}");

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'event' => 'deleted',
        ]);
    }

    public function test_toggling_user_status_persists_activity_log(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->patchJson("/api/system/users/{$user->id}/status", [
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'status_toggled',
            'subject_id' => $user->id,
        ]);
    }

    public function test_toggling_user_status_produces_exactly_one_audit_log(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->patchJson("/api/system/users/{$user->id}/status", [
            'is_active' => false,
        ]);

        $logs = \Spatie\Activitylog\Models\Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('log_name', 'user')
            ->where('event', 'status_toggled')
            ->get();

        $this->assertCount(1, $logs);
    }

    public function test_creating_role_produces_exactly_one_audit_log(): void
    {
        $this->actingAsSuperAdmin();
        $menu = Menu::factory()->create(['type' => Menu::TYPE_BUTTON, 'permission' => 'exact.read']);

        $response = $this->postJson('/api/system/roles', [
            'name' => 'exact-test-role',
            'menu_ids' => [$menu->id],
        ]);

        $this->assertBusinessSuccess($response);

        $roleId = $response->json('data.id');

        $logs = \Spatie\Activitylog\Models\Activity::where('log_name', 'role')
            ->where('subject_id', $roleId)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('created_with_menus', $logs->first()->event);
    }

    public function test_deleting_role_produces_exactly_one_audit_log(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::create(['name' => 'delete-exact', 'guard_name' => 'api']);
        $roleId = $role->id;

        $this->deleteJson("/api/system/roles/{$role->id}");

        $logs = \Spatie\Activitylog\Models\Activity::where('log_name', 'role')
            ->where('event', 'deleted')
            ->where('properties->old->role_id', $roleId)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('deleted', $logs->first()->event);
    }

    public function test_deleting_dictionary_type_persists_activity_log(): void
    {
        $this->actingAsSuperAdmin();
        $type = DictionaryType::factory()->create();

        $this->deleteJson("/api/system/dict-types/{$type->id}");

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'dict_type',
            'event' => 'deleted',
        ]);
    }

    public function test_deleting_dictionary_item_persists_activity_log(): void
    {
        $this->actingAsSuperAdmin();
        $item = DictionaryItem::factory()->create();

        $this->deleteJson("/api/system/dict-items/{$item->id}");

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'dict_item',
            'event' => 'deleted',
        ]);
    }
}
