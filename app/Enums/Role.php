<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case User = 'user';

    public function locale(): string
    {
        return match ($this) {
            self::SuperAdmin => 'roles.superAdmin',
            self::Admin => 'roles.admin',
            self::User => 'roles.user',
        };
    }
}
