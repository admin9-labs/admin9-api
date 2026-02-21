<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_admin_can_list_permissions(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/admin/permissions');

        $this->assertBusinessSuccess($response);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_user_without_permission_cannot_list_permissions(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/admin/permissions');

        $this->assertBusinessError($response, 403);
    }

    public function test_permissions_list_returns_expected_structure(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/admin/permissions');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Each permission should have id and name
        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
    }

    public function test_permissions_list_contains_seeded_permissions(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/admin/permissions');

        $this->assertBusinessSuccess($response);

        $names = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('users.read', $names);
        $this->assertContains('roles.read', $names);
        $this->assertContains('permissions.read', $names);
    }

    public function test_unauthenticated_user_cannot_access_admin_permissions(): void
    {
        $response = $this->getJson('/api/admin/permissions');
        $this->assertBusinessError($response, -1);
    }
}
