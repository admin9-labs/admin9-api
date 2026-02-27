<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $this->assertBusinessSuccess($response);
        $response->assertJsonPath('data.status', 'ok');
    }

    public function test_health_response_structure(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()->assertJsonStructure([
            'success',
            'code',
            'message',
            'data' => ['status'],
            'request_id',
        ]);
    }

    public function test_health_rate_limiting(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/health');
        }

        $response = $this->getJson('/api/health');

        $this->assertBusinessError($response, 429);
    }
}
