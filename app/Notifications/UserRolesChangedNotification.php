<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notify user that their roles have been changed.
 */
class UserRolesChangedNotification extends BaseNotification
{
    protected string $channelKey = 'roles_changed';

    /**
     * @param  list<string>  $oldRoles
     * @param  list<string>  $newRoles
     */
    public function __construct(
        private readonly array $oldRoles,
        private readonly array $newRoles,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Roles Have Been Updated')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your account roles have been updated by an administrator.')
            ->line('Previous roles: '.(implode(', ', $this->oldRoles) ?: 'None'))
            ->line('Current roles: '.(implode(', ', $this->newRoles) ?: 'None'))
            ->line('If you have any questions, please contact your administrator.')
            ->salutation('— Admin9');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'roles_changed',
            'old_roles' => $this->oldRoles,
            'new_roles' => $this->newRoles,
            'message' => 'Your roles have been updated.',
        ];
    }
}
