<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notify user that their account has been enabled or disabled.
 */
class UserStatusChangedNotification extends BaseNotification
{
    protected string $channelKey = 'account_status';

    public function __construct(
        private readonly bool $isActive,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->isActive ? 'enabled' : 'disabled';

        return (new MailMessage)
            ->subject('Your Account Has Been '.ucfirst($status))
            ->greeting("Hello {$notifiable->name},")
            ->line("Your account has been {$status} by an administrator.")
            ->line($this->isActive
                ? 'You can now log in and use the system as usual.'
                : 'You will no longer be able to access the system. If you believe this is a mistake, please contact your administrator.')
            ->salutation('— Admin9');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_status',
            'is_active' => $this->isActive,
            'message' => $this->isActive
                ? 'Your account has been enabled.'
                : 'Your account has been disabled.',
        ];
    }
}
