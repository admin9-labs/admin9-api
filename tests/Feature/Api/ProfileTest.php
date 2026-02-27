<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_update_name_successfully(): void
    {
        $this->actingAsUser();

        $response = $this->patchJson('/api/me', [
            'name' => 'New Name',
        ]);

        $this->assertBusinessSuccess($response);
        $response->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('users', ['id' => auth('api')->id(), 'name' => 'New Name']);
    }

    public function test_update_password_successfully(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('OldPass1word'),
        ]);
        $this->actingAs($user, 'api');

        $response = $this->patchJson('/api/me', [
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
            'current_password' => 'OldPass1word',
        ]);

        $this->assertBusinessSuccess($response);
        $this->assertTrue(Hash::check('NewPass1word', $user->fresh()->password));
    }

    public function test_wrong_current_password_rejected(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('OldPass1word'),
        ]);
        $this->actingAs($user, 'api');

        $response = $this->patchJson('/api/me', [
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
            'current_password' => 'WrongPassword1',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_xss_in_name_rejected(): void
    {
        $this->actingAsUser();

        $response = $this->patchJson('/api/me', [
            'name' => '<script>alert("xss")</script>',
        ]);

        $this->assertBusinessError($response, 422);
    }

    public function test_unauthenticated_access_rejected(): void
    {
        $response = $this->patchJson('/api/me', [
            'name' => 'New Name',
        ]);

        $this->assertBusinessError($response, -1);
        $response->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_empty_request_rejected(): void
    {
        $this->actingAsUser();

        $response = $this->patchJson('/api/me', []);

        $this->assertBusinessError($response, 422);
    }

    public function test_password_without_current_password_rejected(): void
    {
        $this->actingAsUser();

        $response = $this->patchJson('/api/me', [
            'password' => 'NewPass1word',
            'password_confirmation' => 'NewPass1word',
        ]);

        $this->assertBusinessError($response, 422);
    }
}
