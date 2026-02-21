<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class ThrottleTest extends TestCase
{
    public function test_login_is_throttled_after_5_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'throttle@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertBusinessError($response, 429);
    }

    public function test_admin_endpoints_are_throttled_after_60_requests(): void
    {
        $this->actingAsUser(['users.read']);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/admin/users');
        }

        $response = $this->getJson('/api/admin/users');

        $this->assertBusinessError($response, 429);
    }

    public function test_toggle_status_is_throttled_after_10_requests(): void
    {
        $this->actingAsUser(['users.toggleStatus']);

        $target = User::factory()->create(['is_active' => true]);

        for ($i = 0; $i < 10; $i++) {
            $this->patchJson("/api/admin/users/{$target->id}/status", [
                'is_active' => $i % 2 === 0,
            ]);
        }

        $response = $this->patchJson("/api/admin/users/{$target->id}/status", [
            'is_active' => true,
        ]);

        $this->assertBusinessError($response, 429);
    }
}
