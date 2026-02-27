<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Base notification with config-driven channel resolution.
 *
 * Subclasses set $channelKey to match a key in config/notifications.php channels.
 * Downstream projects override channels by editing config or per-notification.
 */
abstract class BaseNotification extends Notification
{
    /**
     * Key in config('notifications.channels') that controls which channels this notification uses.
     */
    protected string $channelKey = 'default';

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return config("notifications.channels.{$this->channelKey}")
            ?? config('notifications.channels.default', ['mail']);
    }

    /**
     * Default database representation. Override in subclasses for richer data.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
