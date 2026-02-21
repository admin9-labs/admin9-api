<?php

namespace Tests\Unit\Filters;

use App\Filters\UserFilter;
use App\Models\User;
use Tests\TestCase;

class UserFilterTest extends TestCase
{
    public function test_filter_by_exact_id(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => true]);

        $results = User::filter(UserFilter::class, ['id' => $user->id])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($user->id, $results->first()->id);
    }

    public function test_filter_by_is_active(): void
    {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);

        $active = User::filter(UserFilter::class, ['is_active' => 1])->get();
        $inactive = User::filter(UserFilter::class, ['is_active' => 0])->get();

        $this->assertCount(2, $active);
        $this->assertCount(1, $inactive);
    }

    public function test_filter_by_name_exact(): void
    {
        User::factory()->create(['name' => 'Alice Smith', 'is_active' => true]);
        User::factory()->create(['name' => 'Bob Jones', 'is_active' => true]);

        $results = User::filter(UserFilter::class, ['name' => 'Alice Smith'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Alice Smith', $results->first()->name);
    }

    public function test_filter_by_email_exact(): void
    {
        User::factory()->create(['email' => 'alice@example.com', 'is_active' => true]);
        User::factory()->create(['email' => 'bob@test.com', 'is_active' => true]);

        $results = User::filter(UserFilter::class, ['email' => 'alice@example.com'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('alice@example.com', $results->first()->email);
    }

    public function test_filter_by_keyword_searches_name_and_email(): void
    {
        User::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@test.com', 'is_active' => true]);
        User::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@alice.com', 'is_active' => true]);
        User::factory()->create(['name' => 'Charlie', 'email' => 'charlie@test.com', 'is_active' => true]);

        $results = User::filter(UserFilter::class, ['keyword' => 'alice'])->get();

        $this->assertCount(2, $results);
    }

    public function test_sort_by_id_ascending(): void
    {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => true]);

        $results = User::filter(UserFilter::class, ['sorts' => 'id'])->get();

        $this->assertTrue($results->first()->id < $results->last()->id);
    }

    public function test_sort_by_id_descending(): void
    {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => true]);

        $results = User::filter(UserFilter::class, ['sorts' => '-id'])->get();

        $this->assertTrue($results->first()->id > $results->last()->id);
    }

    public function test_disallowed_sort_field_is_ignored(): void
    {
        User::factory()->create(['name' => 'Zara', 'is_active' => true]);
        User::factory()->create(['name' => 'Alice', 'is_active' => true]);

        // 'name' is not in allowedSorts, should be ignored
        $results = User::filter(UserFilter::class, ['sorts' => 'name'])->get();

        // Should return in default order (no sort applied)
        $this->assertCount(2, $results);
    }

    public function test_empty_filter_returns_all(): void
    {
        User::factory()->count(3)->create(['is_active' => true]);

        $results = User::filter(UserFilter::class, [])->get();

        $this->assertCount(3, $results);
    }
}
