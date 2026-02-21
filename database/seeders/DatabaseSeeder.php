<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (is_prod()) {
            $this->command->warn('Seeder is disabled in production environment.');
            return;
        }

        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        // Super Admin
        $superAdmin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@admin9.dev',
        ]);
        $superAdmin->assignRole(Role::SuperAdmin->value);

        // Admin
        $admin = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@admin9.dev',
        ]);
        $admin->assignRole(Role::Admin->value);

        // User
        $user = User::factory()->create([
            'name' => 'User',
            'email' => 'user@admin9.dev',
        ]);
        $user->assignRole(Role::User->value);

        User::factory(10)->create()->each(function (User $user) {
            $user->assignRole(Role::User->value);
        });
    }
}
