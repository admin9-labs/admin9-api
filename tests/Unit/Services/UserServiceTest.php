<?php

namespace Tests\Unit\Services;

use App\Enums\Role as RoleEnum;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserService::class);
    }

    // ---- createUser ----

    public function test_create_user_with_default_role(): void
    {
        Role::findOrCreate(RoleEnum::User->value, 'api');

        $user = $this->service->createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertTrue($user->hasRole(RoleEnum::User->value));
    }

    public function test_create_user_with_specific_role(): void
    {
        Role::findOrCreate(RoleEnum::Admin->value, 'api');

        $user = $this->service->createUser([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
        ], RoleEnum::Admin);

        $this->assertTrue($user->hasRole(RoleEnum::Admin->value));
    }

    // ---- updateUser ----

    public function test_update_user_basic_info(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $updated = $this->service->updateUser($user, [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new@example.com', $updated->email);
    }

    public function test_update_user_same_info_no_changes(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $updated = $this->service->updateUser($user, [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $this->assertEquals($user->name, $updated->name);
        $this->assertEquals($user->email, $updated->email);
    }

    public function test_update_user_only_picks_name_and_email(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $updated = $this->service->updateUser($user, [
            'name' => 'Safe Name',
            'email' => $user->email,
            'is_active' => false,  // should be ignored
            'password' => 'hacked', // should be ignored
        ]);

        $this->assertTrue($updated->fresh()->is_active);
        $this->assertTrue(Hash::check('password', $updated->fresh()->password));
    }

    public function test_update_super_admin_throws_exception(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot modify super-admin user');

        $this->service->updateUser($user, ['name' => 'Hacked', 'email' => $user->email]);
    }

    // ---- syncRoles ----

    public function test_sync_roles_assigns_roles(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::findOrCreate('editor', 'api');

        $roles = $this->service->syncRoles($user, [$role->id]);

        $this->assertTrue($user->fresh()->hasRole('editor'));
        $this->assertEquals('editor', $roles->first()->name);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'roles_synced',
            'subject_id' => $user->id,
        ]);
    }

    public function test_sync_roles_rejects_super_admin_role(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $superAdminRole = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot assign super-admin role');

        $this->service->syncRoles($user, [$superAdminRole->id]);
    }

    public function test_sync_roles_rejects_invalid_role_ids(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Invalid role IDs');

        $this->service->syncRoles($user, [99999]);
    }

    public function test_sync_roles_rejects_super_admin_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));
        $role = Role::findOrCreate('editor', 'api');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot modify super-admin user roles');

        $this->service->syncRoles($user, [$role->id]);
    }

    // ---- toggleStatus ----

    public function test_toggle_status_disables_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $operator = User::factory()->create();

        $updated = $this->service->toggleStatus($user, false, $operator->id);

        $this->assertFalse($updated->is_active);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'user', 'event' => 'status_toggled']);
    }

    public function test_toggle_status_skips_when_unchanged(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $operator = User::factory()->create();

        $result = $this->service->toggleStatus($user, true, $operator->id);

        $this->assertTrue($result->is_active);
    }

    public function test_toggle_status_cannot_disable_own_account(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot disable your own account');

        $this->service->toggleStatus($user, false, $user->id);
    }

    public function test_toggle_status_cannot_disable_super_admin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));
        $operator = User::factory()->create();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot disable super-admin users');

        $this->service->toggleStatus($user, false, $operator->id);
    }

    public function test_toggle_status_can_enable_super_admin(): void
    {
        $user = User::factory()->inactive()->create();
        $user->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));
        $operator = User::factory()->create();

        $updated = $this->service->toggleStatus($user, true, $operator->id);

        $this->assertTrue($updated->is_active);
    }

    // ---- resetPassword ----

    public function test_reset_password_changes_password_and_sends_notification(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = User::factory()->create(['is_active' => true]);

        $this->service->resetPassword($user);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'password_reset_requested',
            'subject_id' => $user->id,
        ]);
        \Illuminate\Support\Facades\Notification::assertSentTo($user, \App\Notifications\PasswordResetNotification::class);
    }

    public function test_reset_password_rejects_super_admin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot reset super-admin password via API');

        $this->service->resetPassword($user);
    }

    public function test_toggle_status_only_produces_manual_audit_log(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $operator = User::factory()->create();

        $this->service->toggleStatus($user, false, $operator->id);

        $logs = \Spatie\Activitylog\Models\Activity::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->where('log_name', 'user')
            ->where('event', 'status_toggled')
            ->get();

        $this->assertCount(1, $logs);
    }
}
