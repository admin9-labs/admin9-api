<?php

namespace Tests\Feature\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    public function test_upload_avatar_successfully(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertNotNull($response->json('data.avatar'));
        Storage::disk('public')->assertExists($response->json('data.avatar'));
    }

    public function test_old_avatar_deleted_on_update(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        // Upload first avatar
        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('old.jpg'),
        ]);
        $oldPath = $response->json('data.avatar');

        // Upload new avatar
        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ]);
        $newPath = $response->json('data.avatar');

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_avatar_saved_to_user(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertDatabaseHas('users', [
            'id' => auth('api')->id(),
            'avatar' => $response->json('data.avatar'),
        ]);
    }

    public function test_me_returns_avatar(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response = $this->getJson('/api/me');

        $this->assertBusinessSuccess($response);
        $this->assertNotNull($response->json('data.avatar'));
    }

    public function test_non_image_file_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_oversized_file_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_missing_file_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsUser();

        $response = $this->postJson('/api/me/avatar', []);

        $this->assertBusinessError($response, 422);
    }

    public function test_unauthenticated_access_rejected(): void
    {
        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $this->assertBusinessError($response, -1);
    }
}
