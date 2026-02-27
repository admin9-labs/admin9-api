<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notify user that their password has been successfully reset.
 */
class PasswordResetCompletedNotification extends BaseNotification
{
    protected string $channelKey = 'password_reset_completed';

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Password Has Been Reset')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your password has been successfully reset.')
            ->line('If you did not perform this action, please contact your administrator immediately.')
            ->salutation('— Admin9');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_reset_completed',
            'message' => 'Your password has been successfully reset.',
        ];
    }
}
