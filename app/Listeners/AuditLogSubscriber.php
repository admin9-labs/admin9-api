<?php

namespace App\Listeners;

use App\Events\AuditLoginAttempted;
use App\Events\AuditRoleChanged;
use App\Events\AuditUserChanged;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class AuditLogSubscriber
{
    public function handleRoleChanged(AuditRoleChanged $event): void
    {
        Log::info("Role {$event->action}", $event->toLogContext());
    }

    public function handleUserChanged(AuditUserChanged $event): void
    {
        Log::info("User {$event->action}", $event->toLogContext());
    }

    public function handleLoginAttempted(AuditLoginAttempted $event): void
    {
        $method = in_array($event->action, ['login_failed', 'login_blocked_inactive']) ? 'warning' : 'info';
        Log::{$method}("Login {$event->action}", $event->toLogContext());
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AuditRoleChanged::class, [self::class, 'handleRoleChanged']);
        $events->listen(AuditUserChanged::class, [self::class, 'handleUserChanged']);
        $events->listen(AuditLoginAttempted::class, [self::class, 'handleLoginAttempted']);
    }
}
