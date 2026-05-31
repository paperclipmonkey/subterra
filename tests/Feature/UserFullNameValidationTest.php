<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFullNameValidationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_passes_if_name_is_missing()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(route('users.me.update'), [
            'bio' => 'Something',
            // name is missing
        ]);

        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fails_if_name_is_single_word()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(route('users.me.update'), [
            'name' => 'John',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fails_if_part_is_too_short()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(route('users.me.update'), [
            'name' => 'J Doe',
        ]);

        $response->assertStatus(422);

        $response = $this->putJson(route('users.me.update'), [
            'name' => 'John D',
        ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_passes_if_name_is_valid()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(route('users.me.update'), [
            'name' => 'John Doe',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Doe',
        ]);
    }
}
