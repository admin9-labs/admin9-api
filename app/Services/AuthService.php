<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    public function logLoginFailed(string $email): void
    {
        activity('auth')
            ->event('login_failed')
            ->withProperties(['email' => $email])
            ->log('Login failed');
    }

    public function logLoginBlockedInactive(User $user, string $email): void
    {
        activity('auth')
            ->performedOn($user)
            ->causedBy($user)
            ->event('login_blocked_inactive')
            ->withProperties(['email' => $email])
            ->log('Login blocked, account is inactive');
    }

    public function logLoginSuccess(User $user): void
    {
        activity('auth')
            ->performedOn($user)
            ->causedBy($user)
            ->event('login_success')
            ->log('Login successful');
    }

    public function logLogout(User $user): void
    {
        activity('auth')
            ->performedOn($user)
            ->causedBy($user)
            ->event('logout')
            ->log('User logged out');
    }
}
