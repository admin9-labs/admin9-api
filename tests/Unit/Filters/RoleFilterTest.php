<?php

namespace Tests\Unit\Filters;

use App\Filters\RoleFilter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleFilterTest extends TestCase
{
    public function test_filter_by_exact_id(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'api']);
        Role::create(['name' => 'viewer', 'guard_name' => 'api']);

        $results = Role::filter(RoleFilter::class, ['id' => $role->id])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($role->id, $results->first()->id);
    }

    public function test_filter_by_name_like(): void
    {
        Role::create(['name' => 'content-editor', 'guard_name' => 'api']);
        Role::create(['name' => 'viewer', 'guard_name' => 'api']);

        $results = Role::filter(RoleFilter::class, ['name' => 'editor'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('content-editor', $results->first()->name);
    }

    public function test_sort_by_id_descending(): void
    {
        Role::create(['name' => 'first', 'guard_name' => 'api']);
        Role::create(['name' => 'second', 'guard_name' => 'api']);

        $results = Role::filter(RoleFilter::class, ['sorts' => '-id'])->get();

        $this->assertTrue($results->first()->id > $results->last()->id);
    }

    public function test_sort_by_created_at(): void
    {
        Role::create(['name' => 'older', 'guard_name' => 'api']);
        Role::create(['name' => 'newer', 'guard_name' => 'api']);

        $results = Role::filter(RoleFilter::class, ['sorts' => '-created_at'])->get();

        $this->assertCount(2, $results);
    }

    public function test_empty_filter_returns_all(): void
    {
        Role::create(['name' => 'role-a', 'guard_name' => 'api']);
        Role::create(['name' => 'role-b', 'guard_name' => 'api']);

        $results = Role::filter(RoleFilter::class, [])->get();

        $this->assertCount(2, $results);
    }
}
