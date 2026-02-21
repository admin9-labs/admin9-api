<?php

namespace Tests\Feature\Events;

use App\Events\AuditLoginAttempted;
use App\Events\AuditRoleChanged;
use App\Events\AuditUserChanged;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    // ---- Role events ----

    public function test_creating_role_dispatches_audit_role_changed(): void
    {
        Event::fake(AuditRoleChanged::class);
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/roles', ['name' => 'editor']);

        Event::assertDispatched(AuditRoleChanged::class, function ($e) {
            return $e->action === 'created'
                && $e->metadata['name'] === 'editor'
                && isset($e->metadata['role_id']);
        });
    }

    public function test_updating_role_dispatches_audit_role_changed(): void
    {
        Event::fake(AuditRoleChanged::class);
        $this->actingAsSuperAdmin();
        $role = Role::create(['name' => 'editor', 'guard_name' => 'api']);

        $this->putJson("/api/admin/roles/{$role->id}", ['name' => 'editor-v2']);

        Event::assertDispatched(AuditRoleChanged::class, function ($e) use ($role) {
            return $e->action === 'updated'
                && $e->metadata['role_id'] === $role->id
                && $e->metadata['name'] === 'editor-v2';
        });
    }

    public function test_deleting_role_dispatches_audit_role_changed(): void
    {
        Event::fake(AuditRoleChanged::class);
        $this->actingAsSuperAdmin();
        $role = Role::create(['name' => 'temp-role', 'guard_name' => 'api']);
        $roleId = $role->id;

        $this->deleteJson("/api/admin/roles/{$role->id}");

        Event::assertDispatched(AuditRoleChanged::class, function ($e) use ($roleId) {
            return $e->action === 'deleted'
                && $e->metadata['role_id'] === $roleId
                && $e->metadata['name'] === 'temp-role';
        });
    }

    // ---- User events ----

    public function test_syncing_user_roles_dispatches_audit_user_changed(): void
    {
        Event::fake(AuditUserChanged::class);
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'api']);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => [$role->id],
        ]);

        Event::assertDispatched(AuditUserChanged::class, function ($e) use ($user) {
            return $e->action === 'roles_synced'
                && $e->userId === $user->id
                && $e->metadata['roles'] === ['editor'];
        });
    }

    public function test_toggling_user_status_dispatches_audit_user_changed(): void
    {
        Event::fake(AuditUserChanged::class);
        $admin = $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->patchJson("/api/admin/users/{$user->id}/status", ['is_active' => false]);

        Event::assertDispatched(AuditUserChanged::class, function ($e) use ($user, $admin) {
            return $e->action === 'status_changed'
                && $e->userId === $user->id
                && $e->metadata['is_active'] === false
                && $e->metadata['operator_id'] === $admin->id;
        });
    }

    public function test_resetting_password_dispatches_audit_user_changed(): void
    {
        Event::fake(AuditUserChanged::class);
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->postJson("/api/admin/users/{$user->id}/reset-password");

        Event::assertDispatched(AuditUserChanged::class, function ($e) use ($user) {
            return $e->action === 'password_reset'
                && $e->userId === $user->id;
        });
    }

    // ---- Login events ----

    public function test_successful_login_dispatches_audit_login_attempted(): void
    {
        Event::fake(AuditLoginAttempted::class);
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => 'password', 'is_active' => true]);

        $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'password']);

        Event::assertDispatched(AuditLoginAttempted::class, function ($e) use ($user) {
            return $e->action === 'login_success'
                && $e->userId === $user->id;
        });
    }

    public function test_failed_login_dispatches_audit_login_attempted(): void
    {
        Event::fake(AuditLoginAttempted::class);
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->postJson('/api/auth/login', ['email' => 'wrong@example.com', 'password' => 'wrongpassword']);

        Event::assertDispatched(AuditLoginAttempted::class, function ($e) {
            return $e->action === 'login_failed'
                && $e->userId === null
                && $e->metadata['email'] === 'wrong@example.com';
        });
    }

    public function test_disabled_user_login_dispatches_audit_login_blocked(): void
    {
        Event::fake(AuditLoginAttempted::class);
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['email' => 'disabled@example.com', 'password' => 'password', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => 'disabled@example.com', 'password' => 'password']);

        Event::assertDispatched(AuditLoginAttempted::class, function ($e) use ($user) {
            return $e->action === 'login_blocked_inactive'
                && $e->userId === $user->id
                && $e->metadata['email'] === 'disabled@example.com';
        });
    }

    public function test_updating_user_info_dispatches_audit_user_changed(): void
    {
        Event::fake(AuditUserChanged::class);
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Changed Name',
            'email' => 'changed@example.com',
        ]);

        Event::assertDispatched(AuditUserChanged::class, function ($e) use ($user) {
            return $e->action === 'info_updated'
                && $e->userId === $user->id
                && is_array($e->metadata['changed_fields']);
        });
    }

    public function test_updating_user_with_same_info_does_not_dispatch_audit(): void
    {
        Event::fake(AuditUserChanged::class);
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        Event::assertNotDispatched(AuditUserChanged::class);
    }
}
