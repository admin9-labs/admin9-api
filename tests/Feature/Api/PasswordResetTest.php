<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\PasswordResetCompletedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private function createTokenForUser(User $user): string
    {
        return Password::broker()->createToken($user);
    }

    public function test_valid_token_resets_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_active' => true]);
        $token = $this->createTokenForUser($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertTrue(Hash::check('NewPass1word', $user->fresh()->password));

        Notification::assertSentTo($user, PasswordResetCompletedNotification::class);
    }

    public function test_invalid_token_returns_error(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/password/reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
        ]);

        $this->assertBusinessError($response);
    }

    public function test_missing_fields_returns_validation_error(): void
    {
        $response = $this->postJson('/api/password/reset', []);

        $this->assertBusinessError($response, 422);
    }

    public function test_password_confirmation_mismatch_returns_error(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $this->createTokenForUser($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1word',
            'password_confirmation' => 'DifferentPass1',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_token_is_single_use(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $this->createTokenForUser($user);

        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
        ]);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPass1',
            'password_confirmation' => 'AnotherPass1',
        ]);

        $this->assertBusinessError($response);
    }

    public function test_disabled_user_cannot_reset_password(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $this->createTokenForUser($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
        ]);

        $this->assertBusinessError($response);
    }

    public function test_expired_token_returns_error(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $this->createTokenForUser($user);

        // Manually expire the token by backdating the created_at timestamp
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(61)]);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
        ]);

        $this->assertBusinessError($response);
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $this->createTokenForUser($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ]);

        $this->assertBusinessError($response, 422);
    }
}
