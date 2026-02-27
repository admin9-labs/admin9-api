<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Context;
use Tests\TestCase;

class AddContextTest extends TestCase
{
    public function test_response_has_x_request_id_header(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Request-Id');
    }

    public function test_x_request_id_is_valid_uuid(): void
    {
        $response = $this->getJson('/api/health');

        $requestId = $response->headers->get('X-Request-Id');

        $this->assertNotNull($requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $requestId
        );
    }

    public function test_context_has_required_values_after_request(): void
    {
        $this->getJson('/api/health');

        $this->assertNotNull(Context::get('request_id'));
        $this->assertNotNull(Context::get('request_url'));
        $this->assertNotNull(Context::get('ip'));
        $this->assertNotNull(Context::get('request_method'));
        $this->assertNotNull(Context::get('user_agent'));
    }

    public function test_context_request_method_matches(): void
    {
        $this->getJson('/api/health');

        $this->assertEquals('GET', Context::get('request_method'));
    }

    public function test_context_url_contains_request_path(): void
    {
        $this->getJson('/api/health');

        $this->assertStringContainsString('/api/health', Context::get('request_url'));
    }
}
