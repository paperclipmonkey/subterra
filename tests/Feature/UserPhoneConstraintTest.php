<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPhoneConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_with_unique_phone()
    {
        $user = User::factory()->create();
        $phone = '+447123456789';

        $response = $this->actingAs($user)
            ->putJson("/api/users/{$user->id}", [
                'phone' => $phone,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => $phone,
        ]);
    }

    public function test_user_cannot_use_duplicate_phone()
    {
        $existingUser = User::factory()->create(['phone' => '+447999888777']);
        $newUser = User::factory()->create(['phone' => null]);

        $response = $this->actingAs($newUser)
            ->putJson("/api/users/{$newUser->id}", [
                'phone' => '+447999888777', // Same as existingUser
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_user_can_keep_own_phone_on_update()
    {
        $user = User::factory()->create(['phone' => '+447111222333']);

        $response = $this->actingAs($user)
            ->putJson("/api/users/{$user->id}", [
                'phone' => '+447111222333',
                'bio' => 'Updated bio',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bio' => 'Updated bio',
        ]);
    }
}
