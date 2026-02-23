<?php

namespace Tests\Feature\System;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Tests\TestCase;

class DictionaryItemTest extends TestCase
{
    public function test_can_list_dictionary_items(): void
    {
        $this->actingAsUser(['dictItems.read']);

        $type = DictionaryType::factory()->create();
        DictionaryItem::factory()->count(3)->create(['dictionary_type_id' => $type->id]);

        $response = $this->getJson('/api/system/dict-items');

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination', 'page', 'page_size', 'total'],
            ]);
    }

    public function test_can_create_dictionary_item(): void
    {
        $this->actingAsUser(['dictItems.create']);

        $type = DictionaryType::factory()->create();

        $response = $this->postJson('/api/system/dict-items', [
            'dictionary_type_id' => $type->id,
            'label' => 'Male',
            'value' => 'male',
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('dictionary_items', [
            'dictionary_type_id' => $type->id,
            'label' => 'Male',
            'value' => 'male',
        ]);
    }

    public function test_can_show_dictionary_item(): void
    {
        $this->actingAsUser(['dictItems.read']);

        $item = DictionaryItem::factory()->create();

        $response = $this->getJson("/api/system/dict-items/{$item->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.id', $item->id);
    }

    public function test_can_update_dictionary_item(): void
    {
        $this->actingAsUser(['dictItems.update']);

        $item = DictionaryItem::factory()->create();

        $response = $this->putJson("/api/system/dict-items/{$item->id}", [
            'label' => 'Updated Label',
            'value' => 'updated-value',
        ]);

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('dictionary_items', [
            'id' => $item->id,
            'label' => 'Updated Label',
            'value' => 'updated-value',
        ]);
    }

    public function test_can_delete_dictionary_item(): void
    {
        $this->actingAsUser(['dictItems.delete']);

        $item = DictionaryItem::factory()->create();

        $response = $this->deleteJson("/api/system/dict-items/{$item->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseMissing('dictionary_items', ['id' => $item->id]);
    }

    public function test_cannot_create_duplicate_value_in_same_type(): void
    {
        $this->actingAsUser(['dictItems.create']);

        $type = DictionaryType::factory()->create();
        DictionaryItem::factory()->create([
            'dictionary_type_id' => $type->id,
            'value' => 'male',
        ]);

        $response = $this->postJson('/api/system/dict-items', [
            'dictionary_type_id' => $type->id,
            'label' => 'Male Again',
            'value' => 'male',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_can_create_same_value_in_different_type(): void
    {
        $this->actingAsUser(['dictItems.create']);

        $type1 = DictionaryType::factory()->create();
        $type2 = DictionaryType::factory()->create();

        DictionaryItem::factory()->create([
            'dictionary_type_id' => $type1->id,
            'value' => 'active',
        ]);

        $response = $this->postJson('/api/system/dict-items', [
            'dictionary_type_id' => $type2->id,
            'label' => 'Active',
            'value' => 'active',
        ]);

        $this->assertBusinessSuccess($response);
    }

    public function test_cannot_create_item_with_nonexistent_type(): void
    {
        $this->actingAsUser(['dictItems.create']);

        $response = $this->postJson('/api/system/dict-items', [
            'dictionary_type_id' => 99999,
            'label' => 'Test',
            'value' => 'test',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_user_without_permission_cannot_list_items(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/system/dict-items');

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_create_item(): void
    {
        $this->actingAsUser([]);

        $type = DictionaryType::factory()->create();

        $response = $this->postJson('/api/system/dict-items', [
            'dictionary_type_id' => $type->id,
            'label' => 'Test',
            'value' => 'test',
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_update_item(): void
    {
        $this->actingAsUser([]);

        $item = DictionaryItem::factory()->create();

        $response = $this->putJson("/api/system/dict-items/{$item->id}", [
            'label' => 'Updated',
            'value' => 'updated',
        ]);

        $this->assertBusinessError($response, 403);
    }

    public function test_user_without_permission_cannot_delete_item(): void
    {
        $this->actingAsUser([]);

        $item = DictionaryItem::factory()->create();

        $response = $this->deleteJson("/api/system/dict-items/{$item->id}");

        $this->assertBusinessError($response, 403);
    }

    public function test_cannot_update_value_to_duplicate_in_same_type(): void
    {
        $this->actingAsUser(['dictItems.update']);

        $type = DictionaryType::factory()->create();
        DictionaryItem::factory()->create(['dictionary_type_id' => $type->id, 'value' => 'male']);
        $item = DictionaryItem::factory()->create(['dictionary_type_id' => $type->id, 'value' => 'female']);

        $response = $this->putJson("/api/system/dict-items/{$item->id}", [
            'label' => 'Male',
            'value' => 'male',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/system/dict-items');

        $this->assertBusinessError($response, -1);
    }

    public function test_deleting_dictionary_item_creates_audit_log(): void
    {
        $this->actingAsUser(['dictItems.delete']);

        $item = DictionaryItem::factory()->create();

        $response = $this->deleteJson("/api/system/dict-items/{$item->id}");

        $this->assertBusinessSuccess($response);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'dict_item',
            'event' => 'deleted',
        ]);
    }
}
