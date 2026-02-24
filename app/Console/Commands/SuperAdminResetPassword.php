<?php

namespace App\Console\Commands;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SuperAdminResetPassword extends Command
{
    protected $signature = 'super-admin:reset-password {--email= : The email of the super admin}';

    protected $description = 'Reset a super admin user password';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        if (! $user->hasRole(RoleEnum::SuperAdmin->value)) {
            $this->error("User [{$email}] is not a super admin. This command can only reset super admin passwords.");

            return self::FAILURE;
        }

        $password = $this->secret('New Password');
        $passwordConfirmation = $this->secret('Confirm New Password');

        if ($password !== $passwordConfirmation) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user->update(['password' => Hash::make($password)]);

        $this->info("Password for super admin [{$email}] has been reset successfully.");

        return self::SUCCESS;
    }
}
