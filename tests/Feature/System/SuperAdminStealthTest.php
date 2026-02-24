<?php

namespace Tests\Feature\System;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuperAdminStealthTest extends TestCase
{
    public function test_user_list_excludes_super_admin_users(): void
    {
        $this->actingAsUser(['users.read']);

        User::factory()->create(['name' => 'Normal User', 'is_active' => true]);

        $superAdminUser = User::factory()->create(['name' => 'Super Admin User', 'is_active' => true]);
        $superAdminRole = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');
        $superAdminUser->assignRole($superAdminRole);

        $response = $this->getJson('/api/system/users');

        $this->assertBusinessSuccess($response);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Normal User', $names);
        $this->assertNotContains('Super Admin User', $names);
    }

    public function test_role_list_excludes_super_admin_role(): void
    {
        $this->actingAsUser(['roles.read']);

        Role::findOrCreate('editor', 'api');
        Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');

        $response = $this->getJson('/api/system/roles');

        $this->assertBusinessSuccess($response);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('editor', $names);
        $this->assertNotContains(RoleEnum::SuperAdmin->value, $names);
    }

    public function test_super_admin_also_cannot_see_super_admin_in_user_list(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $normalUser = User::factory()->create(['name' => 'Visible User', 'is_active' => true]);

        $response = $this->getJson('/api/system/users');

        $this->assertBusinessSuccess($response);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($normalUser->id, $ids);
        $this->assertNotContains($superAdmin->id, $ids);
    }

    public function test_super_admin_also_cannot_see_super_admin_in_role_list(): void
    {
        $this->actingAsSuperAdmin();

        Role::findOrCreate('admin', 'api');

        $response = $this->getJson('/api/system/roles');

        $this->assertBusinessSuccess($response);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('admin', $names);
        $this->assertNotContains(RoleEnum::SuperAdmin->value, $names);
    }

    public function test_super_admin_can_update_own_basic_info(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $response = $this->putJson("/api/system/users/{$superAdmin->id}", [
            'name' => 'Updated Super Admin',
            'email' => $superAdmin->email,
        ]);

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.name', 'Updated Super Admin');
    }

    public function test_super_admin_cannot_change_own_roles(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $adminRole = Role::findOrCreate(RoleEnum::Admin->value, 'api');

        $response = $this->putJson("/api/system/users/{$superAdmin->id}", [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role_ids' => [$adminRole->id],
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_super_admin_can_reset_own_password(): void
    {
        Notification::fake();

        $superAdmin = $this->actingAsSuperAdmin();

        $response = $this->postJson("/api/system/users/{$superAdmin->id}/reset-password");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $superAdmin->email,
        ]);

        Notification::assertSentTo($superAdmin, PasswordResetNotification::class);
    }

    public function test_other_user_cannot_modify_super_admin(): void
    {
        $this->actingAsUser(['users.update']);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));

        $response = $this->putJson("/api/system/users/{$superAdmin->id}", [
            'name' => 'Hacked',
            'email' => $superAdmin->email,
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_other_user_cannot_reset_super_admin_password(): void
    {
        $this->actingAsUser(['users.resetPassword']);

        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));

        $response = $this->postJson("/api/system/users/{$superAdmin->id}/reset-password");

        $this->assertBusinessError($response, 403);
    }
}
