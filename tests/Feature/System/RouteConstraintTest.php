<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class RouteConstraintTest extends TestCase
{
    public function test_non_numeric_route_parameter_returns_not_found(): void
    {
        $this->actingAsUser(['users.read']);

        $response = $this->getJson('/api/system/users/abc');

        $this->assertBusinessError($response, 404);
    }
}
