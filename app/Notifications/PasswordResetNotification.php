<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notify user that their password has been reset by an administrator.
 */
class PasswordResetNotification extends Notification
{
    public function __construct(
        private readonly string $password,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password has been reset')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your temporary password is shown below. For security, please change it immediately after logging in.')
            ->line("Temporary password: **{$this->password}**")
            ->line('If you did not request this reset, please contact your administrator immediately.')
            ->salutation('— Admin9');
    }
}
