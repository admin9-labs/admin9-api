<?php

namespace Tests\Feature\System;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_admin_can_list_users(): void
    {
        $this->actingAsUser(['users.read']);

        User::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/system/users');

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination', 'page', 'page_size', 'total'],
            ]);
    }

    public function test_admin_can_view_user(): void
    {
        $this->actingAsUser(['users.read']);

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/system/users/{$target->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.email', $target->email);
    }

    public function test_admin_can_update_user(): void
    {
        $this->actingAsUser(['users.update']);

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->putJson("/api/system/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
        ]);

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $this->actingAsUser(['users.toggleStatus']);

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/api/system/users/{$target->id}/status", [
            'is_active' => false,
        ]);

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_cannot_disable_own_account(): void
    {
        $user = $this->actingAsUser(['users.toggleStatus']);

        $response = $this->patchJson("/api/system/users/{$user->id}/status", [
            'is_active' => false,
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_disable_super_admin(): void
    {
        $this->actingAsSuperAdmin();

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

        $response = $this->patchJson("/api/system/users/{$superAdmin->id}/status", [
            'is_active' => false,
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_admin_can_reset_password(): void
    {
        Notification::fake();

        $this->actingAsUser(['users.resetPassword']);

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/system/users/{$target->id}/reset-password");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $target->email,
        ]);

        Notification::assertSentTo($target, PasswordResetNotification::class);
    }

    public function test_cannot_reset_super_admin_password(): void
    {
        $this->actingAsSuperAdmin();

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

        $response = $this->postJson("/api/system/users/{$superAdmin->id}/reset-password");

        $this->assertBusinessError($response, 403);
    }

    public function test_admin_can_assign_roles_to_user(): void
    {
        $this->actingAsUser(['users.assignRoles']);

        $target = User::factory()->create(['is_active' => true]);
        $role = Role::findOrCreate('editor', 'api');

        $response = $this->putJson("/api/system/users/{$target->id}/assign-roles", [
            'role_ids' => [$role->id],
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertTrue($target->fresh()->hasRole('editor'));
    }

    public function test_assign_roles_requires_permission(): void
    {
        $this->actingAsUser([]);

        $target = User::factory()->create(['is_active' => true]);
        $role = Role::findOrCreate('editor', 'api');

        $response = $this->putJson("/api/system/users/{$target->id}/assign-roles", [
            'role_ids' => [$role->id],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_assign_super_admin_role(): void
    {
        $this->actingAsSuperAdmin();

        $target = User::factory()->create(['is_active' => true]);
        $superAdminRole = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');

        $response = $this->putJson("/api/system/users/{$target->id}/assign-roles", [
            'role_ids' => [$superAdminRole->id],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_assign_roles_to_super_admin_user(): void
    {
        $this->actingAsSuperAdmin();

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

        $adminRole = Role::findOrCreate(RoleEnum::Admin->value, 'api');

        $response = $this->putJson("/api/system/users/{$superAdmin->id}/assign-roles", [
            'role_ids' => [$adminRole->id],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_assign_roles_validates_role_ids_required(): void
    {
        $this->actingAsUser(['users.assignRoles']);

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->putJson("/api/system/users/{$target->id}/assign-roles", []);

        $this->assertBusinessError($response, 422);
    }

    public function test_user_without_permission_cannot_list_users(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/system/users');

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_toggle_status(): void
    {
        $this->actingAsUser([]);

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/api/system/users/{$target->id}/status", [
            'is_active' => false,
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_super_admin_bypasses_permission_check(): void
    {
        $this->actingAsSuperAdmin();

        User::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/system/users');

        $this->assertBusinessSuccess($response);
    }

    public function test_can_filter_users_by_keyword(): void
    {
        $this->actingAsUser(['users.read']);

        User::factory()->create(['name' => 'Alice Smith', 'is_active' => true]);
        User::factory()->create(['name' => 'Bob Jones', 'is_active' => true]);
        User::factory()->create(['name' => 'Alice Wonder', 'is_active' => true]);

        $response = $this->getJson('/api/system/users?keyword=Alice');

        $this->assertBusinessSuccess($response);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_paginate_users(): void
    {
        $this->actingAsUser(['users.read']);

        User::factory()->count(15)->create(['is_active' => true]);

        $response = $this->getJson('/api/system/users?page=2&page_size=5');

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.page_size', 5);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_update_user_validates_xss_in_name(): void
    {
        $this->actingAsUser(['users.update']);

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->putJson("/api/system/users/{$target->id}", [
            'name' => '<script>alert("xss")</script>',
            'email' => $target->email,
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_update_user_validates_duplicate_email(): void
    {
        $this->actingAsUser(['users.update']);

        $userA = User::factory()->create(['is_active' => true]);
        $userB = User::factory()->create(['is_active' => true]);

        $response = $this->putJson("/api/system/users/{$userA->id}", [
            'name' => $userA->name,
            'email' => $userB->email,
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_toggle_status_can_enable_user(): void
    {
        $this->actingAsUser(['users.toggleStatus']);

        $target = User::factory()->create(['is_active' => false]);

        $response = $this->patchJson("/api/system/users/{$target->id}/status", [
            'is_active' => true,
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_update_nonexistent_user_returns_error(): void
    {
        $this->actingAsUser(['users.update']);

        $response = $this->putJson('/api/system/users/99999', [
            'name' => 'Ghost',
            'email' => 'ghost@example.com',
        ]);

        $this->assertBusinessError($response, 404);
    }

    public function test_user_without_permission_cannot_view_user(): void
    {
        $this->actingAsUser([]);
        $target = User::factory()->create();
        $response = $this->getJson("/api/system/users/{$target->id}");
        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_update_user(): void
    {
        $this->actingAsUser([]);
        $target = User::factory()->create();
        $response = $this->putJson("/api/system/users/{$target->id}", [
            'name' => 'Test',
            'email' => $target->email,
        ]);
        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_reset_password(): void
    {
        $this->actingAsUser([]);
        $target = User::factory()->create();
        $response = $this->postJson("/api/system/users/{$target->id}/reset-password");
        $this->assertBusinessError($response, 403);
    }

    public function test_unauthenticated_user_cannot_access_admin_users(): void
    {
        $response = $this->getJson('/api/system/users');
        $this->assertBusinessError($response, -1);
    }

    public function test_cannot_update_super_admin_user_basic_info(): void
    {
        $this->actingAsSuperAdmin();

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(\App\Enums\Role::SuperAdmin->value);

        $response = $this->putJson("/api/system/users/{$superAdmin->id}", [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
        ]);

        $this->assertBusinessError($response, 403);
    }
}
