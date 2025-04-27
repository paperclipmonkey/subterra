<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaveSystemWithCaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_creates_a_cave_system_and_cave_together()
    {
        $payload = [
            'system' => [
                'name' => 'Test System',
                'description' => 'A test cave system',
                'length' => 1234,
                'vertical_range' => 321,
                'slug' => 'test-system',
                'references' => 'Some references',
            ],
            'cave' => [
                'name' => 'Test Cave',
                'description' => 'A test cave',
                'location_name' => 'Test Location',
                'location_country' => 'Testland',
                'location_lat' => 51.12345,
                'location_lng' => -2.12345,
                'location_alt' => 100,
                'access_info' => 'Open',
                'slug' => 'test-cave',
            ]
        ];

        $response = $this->postJson('/api/cave_systems_with_cave', $payload);
        $response->assertCreated();
        $response->assertJsonStructure([
            'system' => [
                'id', 'name', 'slug', 'description', 'length', 'vertical_range', 'caves', 'tags', 'references', 'files', 'created_at', 'updated_at'
            ],
            'cave' => [
                'id', 'slug', 'name', 'description', 'access_info', 'hero_image', 'entrance_image', 'tags', 'location_name', 'location_country', 'location_lat', 'location_lng', 'system', 'trips', 'previously_done'
            ]
        ]);
        $this->assertDatabaseHas('cave_systems', ['name' => 'Test System']);
        $this->assertDatabaseHas('caves', ['name' => 'Test Cave']);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->postJson('/api/cave_systems_with_cave', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'system.name',
            'system.length',
            'system.vertical_range',
            'cave.name',
            'cave.location_name',
            'cave.location_country',
            'cave.location_lat',
            'cave.location_lng',
        ]);
    }
}
