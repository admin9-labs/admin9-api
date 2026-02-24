<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminResetPasswordCommandTest extends TestCase
{
    private function createSuperAdmin(string $email = 'superadmin@test.dev'): User
    {
        $role = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    public function test_can_reset_super_admin_password_with_email_option(): void
    {
        $this->createSuperAdmin();

        $this->artisan('super-admin:reset-password --email=superadmin@test.dev')
            ->expectsQuestion('New Password', 'newpassword')
            ->expectsQuestion('Confirm New Password', 'newpassword')
            ->expectsOutput('Password for super admin [superadmin@test.dev] has been reset successfully.')
            ->assertSuccessful();

        $user = User::where('email', 'superadmin@test.dev')->first();
        $this->assertTrue(Hash::check('newpassword', $user->password));
    }

    public function test_can_reset_super_admin_password_with_interactive_email(): void
    {
        $this->createSuperAdmin();

        $this->artisan('super-admin:reset-password')
            ->expectsQuestion('Email', 'superadmin@test.dev')
            ->expectsQuestion('New Password', 'newpassword')
            ->expectsQuestion('Confirm New Password', 'newpassword')
            ->expectsOutput('Password for super admin [superadmin@test.dev] has been reset successfully.')
            ->assertSuccessful();
    }

    public function test_fails_when_user_not_found(): void
    {
        $this->artisan('super-admin:reset-password --email=nonexistent@test.dev')
            ->expectsOutput('User with email [nonexistent@test.dev] not found.')
            ->assertFailed();
    }

    public function test_fails_when_user_is_not_super_admin(): void
    {
        User::factory()->create(['email' => 'regular@test.dev']);

        $this->artisan('super-admin:reset-password --email=regular@test.dev')
            ->expectsOutput('User [regular@test.dev] is not a super admin. This command can only reset super admin passwords.')
            ->assertFailed();
    }

    public function test_fails_when_passwords_do_not_match(): void
    {
        $this->createSuperAdmin();

        $this->artisan('super-admin:reset-password --email=superadmin@test.dev')
            ->expectsQuestion('New Password', 'newpassword')
            ->expectsQuestion('Confirm New Password', 'different')
            ->expectsOutput('Passwords do not match.')
            ->assertFailed();
    }
}
