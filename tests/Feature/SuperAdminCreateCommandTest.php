<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class SuperAdminCreateCommandTest extends TestCase
{
    public function test_can_create_super_admin(): void
    {
        Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');

        $this->artisan('super-admin:create')
            ->expectsQuestion('Name', 'Test Admin')
            ->expectsQuestion('Email', 'superadmin@test.dev')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm Password', 'secret123')
            ->expectsOutput('Super admin [superadmin@test.dev] created successfully.')
            ->assertSuccessful();

        $user = User::where('email', 'superadmin@test.dev')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test Admin', $user->name);
        $this->assertTrue($user->hasRole(RoleEnum::SuperAdmin->value));
    }

    public function test_fails_when_email_already_exists(): void
    {
        User::factory()->create(['email' => 'existing@test.dev']);

        $this->artisan('super-admin:create')
            ->expectsQuestion('Name', 'Test Admin')
            ->expectsQuestion('Email', 'existing@test.dev')
            ->expectsOutput('User with email [existing@test.dev] already exists.')
            ->assertFailed();
    }

    public function test_fails_when_passwords_do_not_match(): void
    {
        $this->artisan('super-admin:create')
            ->expectsQuestion('Name', 'Test Admin')
            ->expectsQuestion('Email', 'newadmin@test.dev')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm Password', 'different')
            ->expectsOutput('Passwords do not match.')
            ->assertFailed();

        $this->assertNull(User::where('email', 'newadmin@test.dev')->first());
    }
}
