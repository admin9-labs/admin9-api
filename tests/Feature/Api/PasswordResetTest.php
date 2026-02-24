<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
        $user = User::factory()->create(['is_active' => true]);
        $token = $this->createTokenForUser($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_invalid_token_returns_error(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/password/reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $this->assertBusinessError($response, 422);
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
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
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
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'anotherpassword1',
            'password_confirmation' => 'anotherpassword1',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_disabled_user_cannot_reset_password(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $this->createTokenForUser($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $this->assertBusinessError($response, 403);
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
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $this->assertBusinessError($response, 422);
    }
}
