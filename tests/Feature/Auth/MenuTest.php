<?php

namespace Tests\Feature\Auth;

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class MenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_authenticated_user_can_get_menu(): void
    {
        $this->actingAsUser([], 'user');

        $response = $this->getJson('/api/me/menu');

        $this->assertBusinessSuccess($response);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_menu_filters_by_role_for_regular_user(): void
    {
        $this->actingAsUser([], 'user');

        $response = $this->getJson('/api/me/menu');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();

        // Regular user should see dashboard and user menus (roles: ["*"])
        $this->assertContains('Dashboard', $names);
        $this->assertContains('User', $names);

        // Regular user should NOT see system menu (roles: ["super-admin", "admin"])
        $this->assertNotContains('System', $names);
    }

    public function test_menu_filters_by_role_for_admin_user(): void
    {
        $this->actingAsUser([], 'admin');

        $response = $this->getJson('/api/me/menu');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();

        // Admin should see all menus
        $this->assertContains('Dashboard', $names);
        $this->assertContains('User', $names);
        $this->assertContains('System', $names);
    }

    public function test_menu_includes_children(): void
    {
        $this->actingAsUser([], 'user');

        $response = $this->getJson('/api/me/menu');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $dashboard = collect($data)->firstWhere('name', 'Dashboard');

        $this->assertNotNull($dashboard);
        $this->assertNotEmpty($dashboard['children']);

        $childNames = collect($dashboard['children'])->pluck('name')->toArray();
        $this->assertContains('DashboardWorkplace', $childNames);
    }

    public function test_menu_structure_matches_expected_format(): void
    {
        $this->actingAsUser([], 'user');

        $response = $this->getJson('/api/me/menu');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $first = $data[0];

        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('path', $first);
        $this->assertArrayHasKey('meta', $first);
        $this->assertArrayHasKey('children', $first);
        $this->assertArrayHasKey('locale', $first['meta']);
        $this->assertArrayHasKey('requiresAuth', $first['meta']);
    }

    public function test_unauthenticated_user_cannot_get_menu(): void
    {
        $response = $this->getJson('/api/me/menu');

        $this->assertBusinessError($response, -1);
    }

    public function test_inactive_menu_is_not_returned(): void
    {
        Menu::where('name', 'Dashboard')->update(['is_active' => false]);

        $this->actingAsUser([], 'user');

        $response = $this->getJson('/api/me/menu');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();

        $this->assertNotContains('Dashboard', $names);
    }
}
