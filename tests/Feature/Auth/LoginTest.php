<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertBusinessError($response, 401);
    }

    public function test_disabled_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_login_returns_token_info(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertGreaterThan(0, $response->json('data.expires_in'));
    }

    public function test_login_validates_empty_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_login_validates_empty_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_login_does_not_expose_sensitive_fields(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $this->assertBusinessError($response, 401);
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $this->assertBusinessError($response, 422);
    }
}
