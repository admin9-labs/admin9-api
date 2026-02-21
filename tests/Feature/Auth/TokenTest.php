<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class TokenTest extends TestCase
{
    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/me');

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.name', $user->name);
    }

    public function test_me_only_returns_limited_fields(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/me');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('email_verified_at', $data);
        $this->assertArrayHasKey('roles', $data);
        $this->assertIsArray($data['roles']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/auth/logout');

        $this->assertBusinessSuccess($response);
    }

    public function test_unauthenticated_request_returns_error(): void
    {
        $response = $this->getJson('/api/me');

        $this->assertBusinessError($response, -1);
    }

    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        // Login to get a real token
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        // Refresh the token
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/auth/refresh');

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ]);
    }

    public function test_disabled_user_cannot_refresh_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        // Login to get a real token
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        // Disable the user after login
        $user->forceFill(['is_active' => false])->save();

        // Reset the auth guard so the cached user is cleared
        app('auth')->forgetGuards();

        // Try to refresh — should fail
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/auth/refresh');

        $this->assertBusinessError($response, 403);
    }

    public function test_token_is_invalid_after_logout(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        // Logout with the token
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/auth/logout');

        // Reset guard cache
        app('auth')->forgetGuards();

        // Old token should now be invalid
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/me');

        $this->assertBusinessError($response, -1);
    }

    public function test_old_token_is_invalid_after_refresh(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $oldToken = $loginResponse->json('data.access_token');

        // Refresh to get a new token (old one gets blacklisted)
        $refreshResponse = $this->withHeaders(['Authorization' => "Bearer {$oldToken}"])
            ->postJson('/api/auth/refresh');

        $this->assertBusinessSuccess($refreshResponse);

        // Reset guard cache
        app('auth')->forgetGuards();

        // Old token should now be invalid
        $response = $this->withHeaders(['Authorization' => "Bearer {$oldToken}"])
            ->getJson('/api/me');

        $this->assertBusinessError($response, -1);
    }

    public function test_disabled_user_cannot_access_me(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        // Disable the user after login
        $user->forceFill(['is_active' => false])->save();

        // Reset guard cache
        app('auth')->forgetGuards();

        // Disabled user should be blocked by EnsureUserIsActive middleware
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/me');

        $this->assertBusinessError($response, 403);
    }

    public function test_refresh_with_blacklisted_token_fails(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        // Logout to blacklist the token
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/auth/logout');

        // Reset guard cache
        app('auth')->forgetGuards();

        // Try to refresh with the blacklisted token
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/auth/refresh');

        $this->assertBusinessError($response, 401);
    }
}
