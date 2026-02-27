<?php

namespace Tests\Feature\System;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Tests\TestCase;

class DictionaryTypeTest extends TestCase
{
    public function test_can_list_dictionary_types(): void
    {
        $this->actingAsUser(['dictTypes.read']);

        DictionaryType::factory()->count(3)->create();

        $response = $this->getJson('/api/system/dict-types');

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination', 'page', 'page_size', 'total'],
            ]);
    }

    public function test_can_create_dictionary_type(): void
    {
        $this->actingAsUser(['dictTypes.create']);

        $response = $this->postJson('/api/system/dict-types', [
            'name' => 'Gender',
            'code' => 'gender',
            'description' => 'Gender options',
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('dictionary_types', [
            'name' => 'Gender',
            'code' => 'gender',
        ]);
    }

    public function test_can_show_dictionary_type(): void
    {
        $this->actingAsUser(['dictTypes.read']);

        $type = DictionaryType::factory()->create();

        $response = $this->getJson("/api/system/dict-types/{$type->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.id', $type->id);
    }

    public function test_can_update_dictionary_type(): void
    {
        $this->actingAsUser(['dictTypes.update']);

        $type = DictionaryType::factory()->create();

        $response = $this->putJson("/api/system/dict-types/{$type->id}", [
            'name' => 'Updated Name',
            'code' => 'updated-code',
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('dictionary_types', [
            'id' => $type->id,
            'name' => 'Updated Name',
            'code' => 'updated-code',
        ]);
    }

    public function test_can_delete_dictionary_type(): void
    {
        $this->actingAsUser(['dictTypes.delete']);

        $type = DictionaryType::factory()->create();

        $response = $this->deleteJson("/api/system/dict-types/{$type->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseMissing('dictionary_types', ['id' => $type->id]);
    }

    public function test_cannot_delete_type_with_items(): void
    {
        $this->actingAsUser(['dictTypes.delete']);

        $type = DictionaryType::factory()->create();
        DictionaryItem::factory()->create(['dictionary_type_id' => $type->id]);

        $response = $this->deleteJson("/api/system/dict-types/{$type->id}");

        $this->assertBusinessError($response);

        $this->assertDatabaseHas('dictionary_types', ['id' => $type->id]);
    }

    public function test_cannot_create_duplicate_name(): void
    {
        $this->actingAsUser(['dictTypes.create']);

        DictionaryType::factory()->create(['name' => 'Gender', 'code' => 'gender']);

        $response = $this->postJson('/api/system/dict-types', [
            'name' => 'Gender',
            'code' => 'gender2',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_cannot_create_duplicate_code(): void
    {
        $this->actingAsUser(['dictTypes.create']);

        DictionaryType::factory()->create(['name' => 'Gender', 'code' => 'gender']);

        $response = $this->postJson('/api/system/dict-types', [
            'name' => 'Gender2',
            'code' => 'gender',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_can_get_items_by_code(): void
    {
        $this->actingAsUser(['dictTypes.read']);

        $type = DictionaryType::factory()->create(['code' => 'status', 'is_active' => true]);
        DictionaryItem::factory()->create(['dictionary_type_id' => $type->id, 'label' => 'Active', 'value' => 'active', 'is_active' => true]);
        DictionaryItem::factory()->create(['dictionary_type_id' => $type->id, 'label' => 'Inactive', 'value' => 'inactive', 'is_active' => false]);

        $response = $this->getJson('/api/system/dict-types/status/items');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active', $data[0]['label']);
    }

    public function test_get_items_by_nonexistent_code_returns_404(): void
    {
        $this->actingAsUser(['dictTypes.read']);

        $response = $this->getJson('/api/system/dict-types/nonexistent/items');

        $this->assertBusinessError($response, 404);
    }

    public function test_user_without_permission_cannot_list_types(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/system/dict-types');

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_create_type(): void
    {
        $this->actingAsUser([]);

        $response = $this->postJson('/api/system/dict-types', [
            'name' => 'Test',
            'code' => 'test',
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_update_type(): void
    {
        $this->actingAsUser([]);

        $type = DictionaryType::factory()->create();

        $response = $this->putJson("/api/system/dict-types/{$type->id}", [
            'name' => 'Updated',
            'code' => 'updated',
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_delete_type(): void
    {
        $this->actingAsUser([]);

        $type = DictionaryType::factory()->create();

        $response = $this->deleteJson("/api/system/dict-types/{$type->id}");

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_update_to_duplicate_name(): void
    {
        $this->actingAsUser(['dictTypes.update']);

        DictionaryType::factory()->create(['name' => 'Existing', 'code' => 'existing']);
        $type = DictionaryType::factory()->create(['name' => 'Other', 'code' => 'other']);

        $response = $this->putJson("/api/system/dict-types/{$type->id}", [
            'name' => 'Existing',
            'code' => 'other',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_cannot_update_to_duplicate_code(): void
    {
        $this->actingAsUser(['dictTypes.update']);

        DictionaryType::factory()->create(['name' => 'Existing', 'code' => 'existing']);
        $type = DictionaryType::factory()->create(['name' => 'Other', 'code' => 'other']);

        $response = $this->putJson("/api/system/dict-types/{$type->id}", [
            'name' => 'Other',
            'code' => 'existing',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/system/dict-types');

        $this->assertBusinessError($response, -1);
    }

    public function test_deleting_dictionary_type_creates_audit_log(): void
    {
        $this->actingAsUser(['dictTypes.delete']);

        $type = DictionaryType::factory()->create();

        $response = $this->deleteJson("/api/system/dict-types/{$type->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'dict_type',
            'event' => 'deleted',
        ]);
    }
}
