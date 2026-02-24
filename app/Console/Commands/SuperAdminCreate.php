<?php

namespace App\Console\Commands;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SuperAdminCreate extends Command
{
    protected $signature = 'super-admin:create';

    protected $description = 'Create a super admin user';

    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email');

        if (User::where('email', $email)->exists()) {
            $this->error("User with email [{$email}] already exists.");

            return self::FAILURE;
        }

        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm Password');

        if ($password !== $passwordConfirmation) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $role = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');
        $user->assignRole($role);

        $this->info("Super admin [{$email}] created successfully.");

        return self::SUCCESS;
    }
}
