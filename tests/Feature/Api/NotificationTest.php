<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    // -------------------------------------------------------
    // Channel abstraction tests
    // -------------------------------------------------------

    public function test_base_notification_uses_default_channels_from_config(): void
    {
        config(['notifications.channels.default' => ['mail', 'database']]);

        $notification = new class extends BaseNotification {};
        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['mail', 'database'], $channels);
    }

    public function test_base_notification_falls_back_to_mail_when_config_missing(): void
    {
        config(['notifications.channels' => []]);

        $notification = new class extends BaseNotification {};
        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['mail'], $channels);
    }

    public function test_password_reset_uses_configured_channels(): void
    {
        config(['notifications.channels.password_reset' => ['mail']]);

        $notification = new PasswordResetNotification('token', 'test@example.com');
        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['mail'], $channels);
    }

    public function test_password_reset_can_include_database_channel_via_config(): void
    {
        config(['notifications.channels.password_reset' => ['mail', 'database']]);

        $notification = new PasswordResetNotification('token', 'test@example.com');
        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['mail', 'database'], $channels);
    }

    public function test_password_reset_to_array_returns_expected_data(): void
    {
        $notification = new PasswordResetNotification('token', 'test@example.com');
        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('password_reset', $data['type']);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_password_reset_to_mail_returns_mail_message(): void
    {
        $notification = new PasswordResetNotification('test-token', 'user@example.com');
        $user = User::factory()->make(['name' => 'Test User']);

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    // -------------------------------------------------------
    // Database notification stored when channel enabled
    // -------------------------------------------------------

    public function test_database_notification_is_stored_when_channel_enabled(): void
    {
        config(['notifications.channels.password_reset' => ['database']]);

        $user = User::factory()->create(['is_active' => true]);
        $user->notify(new PasswordResetNotification('tok', $user->email));

        $this->assertDatabaseCount('notifications', 1);
        $this->assertCount(1, $user->fresh()->notifications);
        $this->assertEquals('password_reset', $user->notifications->first()->data['type']);
    }

    // -------------------------------------------------------
    // Notification API endpoint tests
    // -------------------------------------------------------

    public function test_list_notifications_requires_auth(): void
    {
        $response = $this->getJson('/api/notifications');

        $this->assertBusinessError($response, -1);
    }

    public function test_list_notifications_returns_paginated_results(): void
    {
        $user = $this->actingAsUser();

        config(['notifications.channels.password_reset' => ['database']]);
        $user->notify(new PasswordResetNotification('tok1', $user->email));
        $user->notify(new PasswordResetNotification('tok2', $user->email));

        $response = $this->getJson('/api/notifications');

        $this->assertBusinessSuccess($response);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $user = $this->actingAsUser();

        config(['notifications.channels.password_reset' => ['database']]);
        $user->notify(new PasswordResetNotification('tok', $user->email));

        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at);

        $response = $this->patchJson("/api/notifications/{$notification->id}/read");

        $this->assertBusinessSuccess($response);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_nonexistent_notification_returns_error(): void
    {
        $this->actingAsUser();

        $response = $this->patchJson('/api/notifications/nonexistent-uuid/read');

        $this->assertBusinessError($response, 404);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = $this->actingAsUser();

        config(['notifications.channels.password_reset' => ['database']]);
        $user->notify(new PasswordResetNotification('tok1', $user->email));
        $user->notify(new PasswordResetNotification('tok2', $user->email));

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $response = $this->postJson('/api/notifications/read-all');

        $this->assertBusinessSuccess($response);
        $response->assertJsonPath('data.updated', 2);
        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);

        config(['notifications.channels.password_reset' => ['database']]);
        $otherUser->notify(new PasswordResetNotification('tok', $otherUser->email));

        $notification = $otherUser->notifications()->first();

        $this->actingAsUser();

        $response = $this->patchJson("/api/notifications/{$notification->id}/read");

        $this->assertBusinessError($response, 404);
    }
}
