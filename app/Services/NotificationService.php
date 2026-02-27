<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $user->notifications()->find($notificationId);

        if (! $notification) {
            throw new BusinessException('Notification not found', 404);
        }

        $notification->markAsRead();

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
