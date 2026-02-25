<?php

namespace App\Console\Commands;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class SuperAdminCreate extends Command
{
    use ConfirmableTrait;

    protected $signature = 'super-admin:create {--force : Force the operation to run in production}';

    protected $description = 'Create a super admin user';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

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

        $validator = Validator::make(
            ['password' => $password],
            ['password' => [Password::min(8)->mixedCase()->numbers()->max(128)]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $role = Role::findOrCreate(RoleEnum::SuperAdmin->value, 'api');
        $user->assignRole($role);

        activity('super_admin')
            ->causedByAnonymous()
            ->performedOn($user)
            ->withProperties(['email' => $email])
            ->log('Super admin created via CLI');

        $this->info("Super admin [{$email}] created successfully.");

        return self::SUCCESS;
    }
}
