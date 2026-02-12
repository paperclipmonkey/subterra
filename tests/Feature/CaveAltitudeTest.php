<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaveAltitudeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TagSeeder::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_cave_altitude()
    {
        $user = User::factory()->dataAdmin()->create();
        $cave = Cave::factory()->create([
            'location_alt' => 100.5,
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/caves/'.$cave->slug, [
                'name' => 'Updated Cave Name', // valid required field
                'location_alt' => 250.75,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('caves', [
            'id' => $cave->id,
            'location_alt' => 250.75,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_set_altitude_to_null()
    {
        $user = User::factory()->dataAdmin()->create();
        $cave = Cave::factory()->create([
            'location_alt' => 100.5,
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/caves/'.$cave->slug, [
                'name' => 'Updated Cave Name',
                'location_alt' => null,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('caves', [
            'id' => $cave->id,
            'location_alt' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_altitude_is_numeric()
    {
        $user = User::factory()->dataAdmin()->create();
        $cave = Cave::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/caves/'.$cave->slug, [
                'name' => 'Updated Cave Name',
                'location_alt' => 'not-a-number',
            ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['location_alt']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_cave_with_altitude()
    {
        $user = User::factory()->dataAdmin()->create();

        // Mocking required fields for creation
        $caveData = Cave::factory()->make([
            'location_alt' => 300.25,
            'location_name' => 'Test Location',
            'location_lng' => -2.5,
        ])->toArray();
        // Factory make might include ID or timestamps which we don't want, but usually it doesn't.
        // Also factory might include relationships, need to be careful.
        // Simplest is to just manually define the array.

        $system = \App\Models\CaveSystem::factory()->create();

        $data = [
            'name' => 'New Cave with Altitude',
            'cave_system_id' => $system->id,
            'location_name' => 'Test Location',
            'location_lng' => -2.5,
            'location_lat' => 54.5,
            'location_country' => 'UK',
            'location_alt' => 300.25,
            'description' => 'Test description',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/caves', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('caves', [
            'name' => 'New Cave with Altitude',
            'location_alt' => 300.25,
        ]);
    }
}
