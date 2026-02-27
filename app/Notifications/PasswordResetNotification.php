<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notify user that a password reset has been requested by an administrator.
 */
class PasswordResetNotification extends BaseNotification
{
    protected string $channelKey = 'password_reset';

    public function __construct(
        private readonly string $token,
        private readonly string $email,
    ) {}

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_reset',
            'message' => 'An administrator has requested a password reset for your account.',
        ];
    }
}
