<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notify user that a password reset has been requested by an administrator.
 */
class PasswordResetNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly string $email,
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
        $url = config('app.frontend_url').'/reset-password?token='.$this->token.'&email='.urlencode($this->email);
        $expire = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->greeting("Hello {$notifiable->name},")
            ->line('An administrator has requested a password reset for your account. Click the button below to set a new password.')
            ->action('Reset Password', $url)
            ->line("This link will expire in {$expire} minutes.")
            ->line('If you did not expect this reset, please contact your administrator immediately.')
            ->salutation('— Admin9');
    }
}
