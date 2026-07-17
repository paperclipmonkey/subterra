<?php

declare(strict_types=1);

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

    public function test_national_format_phone_is_normalised_to_e164()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/users/{$user->id}", [
                'phone' => '07123 456 789',
            ]);

        $response->assertStatus(200);
        // Stored canonically as +44… so Twilio SMS/voice always gets E.164.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+447123456789',
        ]);
    }

    public function test_duplicate_phone_in_other_format_is_rejected()
    {
        $existingUser = User::factory()->create(['phone' => '+447999888777']);
        $newUser = User::factory()->create(['phone' => null]);

        // Same number in national 07… form must hit the uniqueness rule.
        $response = $this->actingAs($newUser)
            ->putJson("/api/users/{$newUser->id}", [
                'phone' => '07999888777',
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
