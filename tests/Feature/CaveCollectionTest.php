<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaveCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cave_api_returns_linked_collections()
    {
        // Seed necessary tags
        \App\Models\Tag::create(['tag' => 'Previously Done', 'category' => 'system', 'type' => 'status']);
        \App\Models\Tag::create(['tag' => 'Not Done Yet', 'category' => 'system', 'type' => 'status']);

        // Create user
        $user = User::factory()->create();

        // Create a cave
        $cave = Cave::factory()->create();

        // Create a collection
        $collection = Collection::factory()->create([
            'user_id' => $user->id
        ]);

        // Link cave to collection
        $collection->caves()->attach($cave->id);

        // Call the API using the slug
        $response = $this->actingAs($user)->getJson('/api/caves/' . $cave->slug);

        // Assert response
        $response->assertStatus(200);
        
        // Assert collections are present in the response
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'collections' => [
                    '*' => [
                        'id',
                        'name',
                        'slug'
                    ]
                ]
            ]
        ]);

        // Assert specific collection data
        $response->assertJsonFragment([
            'name' => $collection->name,
            'slug' => $collection->slug
        ]);
    }
}
