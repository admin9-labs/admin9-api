<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notify user that their profile has been updated by an administrator.
 */
class UserProfileUpdatedNotification extends BaseNotification
{
    protected string $channelKey = 'profile_updated';

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        private readonly array $changes,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $fields = implode(', ', array_keys($this->changes));

        return (new MailMessage)
            ->subject('Your Profile Has Been Updated')
            ->greeting("Hello {$notifiable->name},")
            ->line('An administrator has updated your profile.')
            ->line("Changed fields: {$fields}")
            ->line('If you did not expect this change, please contact your administrator.')
            ->salutation('— Admin9');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'profile_updated',
            'changes' => array_keys($this->changes),
            'message' => 'Your profile has been updated by an administrator.',
        ];
    }
}
