<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Context;

class AuthService
{
    public function logLoginFailed(string $email, ?string $userAgent): void
    {
        activity('auth')
            ->event('login_failed')
            ->withProperties(array_filter([
                'email' => $email,
                'ip' => Context::get('ip'),
                'browser' => $userAgent,
            ], fn ($v) => $v !== null))
            ->log('login_failed');
    }

    public function logLoginBlockedInactive(User $user, string $email, ?string $userAgent): void
    {
        activity('auth')
            ->performedOn($user)
            ->causedBy($user)
            ->event('login_blocked_inactive')
            ->withProperties(array_filter([
                'email' => $email,
                'ip' => Context::get('ip'),
                'browser' => $userAgent,
            ], fn ($v) => $v !== null))
            ->log('login_blocked_inactive');
    }

    public function logLoginSuccess(User $user, ?string $userAgent): void
    {
        activity('auth')
            ->performedOn($user)
            ->causedBy($user)
            ->event('login_success')
            ->withProperties(array_filter([
                'ip' => Context::get('ip'),
                'browser' => $userAgent,
            ], fn ($v) => $v !== null))
            ->log('login_success');
    }
}
