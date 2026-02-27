<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\PasswordResetCompletedNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\UserProfileUpdatedNotification;
use App\Notifications\UserRolesChangedNotification;
use App\Notifications\UserStatusChangedNotification;
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

        $this->assertBusinessError($response);
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

        $this->assertBusinessError($response);
    }

    // -------------------------------------------------------
    // Delete notification endpoint tests
    // -------------------------------------------------------

    public function test_delete_notification(): void
    {
        $user = $this->actingAsUser();

        config(['notifications.channels.password_reset' => ['database']]);
        $user->notify(new PasswordResetNotification('tok', $user->email));

        $notification = $user->notifications()->first();

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $this->assertBusinessSuccess($response);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_delete_nonexistent_notification_returns_error(): void
    {
        $this->actingAsUser();

        $response = $this->deleteJson('/api/notifications/nonexistent-uuid');

        $this->assertBusinessError($response);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);

        config(['notifications.channels.password_reset' => ['database']]);
        $otherUser->notify(new PasswordResetNotification('tok', $otherUser->email));

        $notification = $otherUser->notifications()->first();

        $this->actingAsUser();

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $this->assertBusinessError($response);
    }

    // -------------------------------------------------------
    // Unread count endpoint tests
    // -------------------------------------------------------

    public function test_unread_count_returns_zero_when_no_notifications(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/notifications/unread-count');

        $this->assertBusinessSuccess($response);
        $response->assertJsonPath('data.count', 0);
    }

    public function test_unread_count_returns_correct_count(): void
    {
        $user = $this->actingAsUser();

        config(['notifications.channels.password_reset' => ['database']]);
        $user->notify(new PasswordResetNotification('tok1', $user->email));
        $user->notify(new PasswordResetNotification('tok2', $user->email));

        $response = $this->getJson('/api/notifications/unread-count');

        $this->assertBusinessSuccess($response);
        $response->assertJsonPath('data.count', 2);
    }

    public function test_unread_count_excludes_read_notifications(): void
    {
        $user = $this->actingAsUser();

        config(['notifications.channels.password_reset' => ['database']]);
        $user->notify(new PasswordResetNotification('tok1', $user->email));
        $user->notify(new PasswordResetNotification('tok2', $user->email));

        $user->notifications()->first()->markAsRead();

        $response = $this->getJson('/api/notifications/unread-count');

        $this->assertBusinessSuccess($response);
        $response->assertJsonPath('data.count', 1);
    }

    // -------------------------------------------------------
    // UserStatusChangedNotification tests
    // -------------------------------------------------------

    public function test_status_changed_notification_to_array_disabled(): void
    {
        $notification = new UserStatusChangedNotification(false);
        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('account_status', $data['type']);
        $this->assertFalse($data['is_active']);
        $this->assertStringContainsString('disabled', $data['message']);
    }

    public function test_status_changed_notification_to_array_enabled(): void
    {
        $notification = new UserStatusChangedNotification(true);
        $data = $notification->toArray(new \stdClass);

        $this->assertTrue($data['is_active']);
        $this->assertStringContainsString('enabled', $data['message']);
    }

    public function test_status_changed_notification_to_mail(): void
    {
        $notification = new UserStatusChangedNotification(false);
        $user = User::factory()->make(['name' => 'Test']);

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_status_changed_notification_uses_configured_channels(): void
    {
        config(['notifications.channels.account_status' => ['database']]);

        $notification = new UserStatusChangedNotification(false);
        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    // -------------------------------------------------------
    // UserRolesChangedNotification tests
    // -------------------------------------------------------

    public function test_roles_changed_notification_to_array(): void
    {
        $notification = new UserRolesChangedNotification(['User'], ['Admin', 'Editor']);
        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('roles_changed', $data['type']);
        $this->assertEquals(['User'], $data['old_roles']);
        $this->assertEquals(['Admin', 'Editor'], $data['new_roles']);
    }

    public function test_roles_changed_notification_to_mail(): void
    {
        $notification = new UserRolesChangedNotification(['User'], ['Admin']);
        $user = User::factory()->make(['name' => 'Test']);

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    // -------------------------------------------------------
    // UserProfileUpdatedNotification tests
    // -------------------------------------------------------

    public function test_profile_updated_notification_to_array(): void
    {
        $notification = new UserProfileUpdatedNotification(['name' => 'New Name', 'email' => 'new@example.com']);
        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('profile_updated', $data['type']);
        $this->assertEquals(['name', 'email'], $data['changes']);
    }

    public function test_profile_updated_notification_to_mail(): void
    {
        $notification = new UserProfileUpdatedNotification(['name' => 'New Name']);
        $user = User::factory()->make(['name' => 'Test']);

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_profile_updated_notification_uses_database_channel(): void
    {
        config(['notifications.channels.profile_updated' => ['database']]);

        $notification = new UserProfileUpdatedNotification(['name' => 'X']);
        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    // -------------------------------------------------------
    // PasswordResetCompletedNotification tests
    // -------------------------------------------------------

    public function test_password_reset_completed_to_array(): void
    {
        $notification = new PasswordResetCompletedNotification;
        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('password_reset_completed', $data['type']);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_password_reset_completed_to_mail(): void
    {
        $notification = new PasswordResetCompletedNotification;
        $user = User::factory()->make(['name' => 'Test']);

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }
}
