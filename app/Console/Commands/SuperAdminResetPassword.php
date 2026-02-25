<?php

namespace App\Console\Commands;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class SuperAdminResetPassword extends Command
{
    use ConfirmableTrait;

    protected $signature = 'super-admin:reset-password {--email= : The email of the super admin} {--force : Force the operation to run in production}';

    protected $description = 'Reset a super admin user password';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

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

        $user->update(['password' => Hash::make($password)]);

        activity('super_admin')
            ->causedByAnonymous()
            ->performedOn($user)
            ->withProperties(['email' => $email])
            ->log('Super admin password reset via CLI');

        $this->info("Password for super admin [{$email}] has been reset successfully.");

        return self::SUCCESS;
    }
}
