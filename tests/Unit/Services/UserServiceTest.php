<?php

namespace Tests\Unit\Services;

use App\Enums\Role as RoleEnum;
use App\Events\AuditUserChanged;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Event;
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
        Event::fake([AuditUserChanged::class]);

        $user = User::factory()->create(['is_active' => true]);

        $updated = $this->service->updateUser($user, [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new@example.com', $updated->email);
        Event::assertDispatched(AuditUserChanged::class, fn ($e) => $e->action === 'info_updated'
            && in_array('name', $e->metadata['changed_fields'])
            && in_array('email', $e->metadata['changed_fields']));
    }

    public function test_update_user_same_info_does_not_dispatch_audit(): void
    {
        Event::fake([AuditUserChanged::class]);

        $user = User::factory()->create(['is_active' => true]);

        $this->service->updateUser($user, [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        Event::assertNotDispatched(AuditUserChanged::class);
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

    public function test_update_user_with_role_sync(): void
    {
        Event::fake([AuditUserChanged::class]);

        $user = User::factory()->create(['is_active' => true]);
        $role = Role::findOrCreate('editor', 'api');

        $updated = $this->service->updateUser($user, [
            'name' => $user->name,
            'email' => $user->email,
        ], [$role->id]);

        $this->assertTrue($updated->hasRole('editor'));
        Event::assertDispatched(AuditUserChanged::class, fn ($e) => $e->action === 'roles_synced');
    }

    public function test_update_super_admin_throws_exception(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot modify super-admin user');

        $this->service->updateUser($user, ['name' => 'Hacked', 'email' => $user->email]);
    }

    public function test_sync_roles_rejects_super_admin_role(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $superAdminRole = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot assign super-admin role');

        $this->service->updateUser($user, [
            'name' => $user->name,
            'email' => $user->email,
        ], [$superAdminRole->id]);
    }

    public function test_sync_roles_rejects_invalid_role_ids(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Invalid role IDs');

        $this->service->updateUser($user, [
            'name' => $user->name,
            'email' => $user->email,
        ], [99999]);
    }

    // ---- toggleStatus ----

    public function test_toggle_status_disables_user(): void
    {
        Event::fake([AuditUserChanged::class]);

        $user = User::factory()->create(['is_active' => true]);
        $operator = User::factory()->create();

        $updated = $this->service->toggleStatus($user, false, $operator->id);

        $this->assertFalse($updated->is_active);
        Event::assertDispatched(AuditUserChanged::class, fn ($e) => $e->action === 'status_changed');
    }

    public function test_toggle_status_skips_when_unchanged(): void
    {
        Event::fake([AuditUserChanged::class]);

        $user = User::factory()->create(['is_active' => true]);
        $operator = User::factory()->create();

        $this->service->toggleStatus($user, true, $operator->id);

        Event::assertNotDispatched(AuditUserChanged::class);
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

    public function test_reset_password_returns_new_password(): void
    {
        Event::fake([AuditUserChanged::class]);

        $user = User::factory()->create(['is_active' => true]);

        $newPassword = $this->service->resetPassword($user);

        $this->assertIsString($newPassword);
        $this->assertEquals(16, strlen($newPassword));
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        Event::assertDispatched(AuditUserChanged::class, fn ($e) => $e->action === 'password_reset');
    }

    public function test_reset_password_rejects_super_admin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api'));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot reset super-admin password via API');

        $this->service->resetPassword($user);
    }
}
