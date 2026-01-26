<?php

namespace Tests\Feature;

use App\Models\ApiInteraction;
use App\Models\Cave;
use App\Models\Collection;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TagSeeder::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_cave_show_interactions()
    {
        $user = User::factory()->create();
        $cave = Cave::factory()->create();

        $this->actingAs($user);
        
        $this->assertEquals(0, ApiInteraction::count());
        
        $this->get('/api/caves/' . $cave->slug);
        
        $this->assertEquals(1, ApiInteraction::count());
        
        $interaction = ApiInteraction::first();
        $this->assertEquals(Cave::class, $interaction->trackable_type);
        $this->assertEquals($cave->id, $interaction->trackable_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_trip_show_interactions()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);

        $this->actingAs($user);
        
        $this->assertEquals(0, ApiInteraction::count());
        
        $this->get('/api/trips/' . $trip->short_id);
        
        $this->assertEquals(1, ApiInteraction::count());
        
        $interaction = ApiInteraction::first();
        $this->assertEquals(Trip::class, $interaction->trackable_type);
        $this->assertEquals($trip->id, $interaction->trackable_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_collection_show_interactions()
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create();

        $this->actingAs($user);
        
        $this->assertEquals(0, ApiInteraction::count());
        
        $this->get('/api/collections/' . $collection->slug);
        
        $this->assertEquals(1, ApiInteraction::count());
        
        $interaction = ApiInteraction::first();
        $this->assertEquals(Collection::class, $interaction->trackable_type);
        $this->assertEquals($collection->id, $interaction->trackable_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_track_index_requests()
    {
        $user = User::factory()->create();
        Cave::factory()->count(3)->create();

        $this->actingAs($user);
        
        $this->get('/api/caves');
        
        $this->assertEquals(0, ApiInteraction::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_track_non_get_requests()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $cave = Cave::factory()->create();

        $this->actingAs($admin);
        
        $this->putJson('/api/caves/' . $cave->slug, [
            'name' => 'Updated Cave',
        ]);
        
        $this->assertEquals(0, ApiInteraction::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_multiple_interactions_for_same_resource()
    {
        $user = User::factory()->create();
        $cave = Cave::factory()->create();

        $this->actingAs($user);
        
        $this->get('/api/caves/' . $cave->slug);
        $this->get('/api/caves/' . $cave->slug);
        $this->get('/api/caves/' . $cave->slug);
        
        $this->assertEquals(3, ApiInteraction::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_get_popular_records()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        
        $cave1 = Cave::factory()->create(['name' => 'Popular Cave']);
        $cave2 = Cave::factory()->create(['name' => 'Less Popular Cave']);
        $trip = Trip::factory()->create(['name' => 'Popular Trip', 'visibility' => 'public']);

        $this->actingAs($user);
        
        // Create interactions
        for ($i = 0; $i < 10; $i++) {
            $this->get('/api/caves/' . $cave1->slug);
        }
        
        for ($i = 0; $i < 5; $i++) {
            $this->get('/api/caves/' . $cave2->slug);
        }
        
        for ($i = 0; $i < 8; $i++) {
            $this->get('/api/trips/' . $trip->short_id);
        }

        $this->actingAs($admin);
        
        $response = $this->getJson('/api/admin/dashboard/popular-records');
        
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'name',
                    'total_interactions',
                    'sparkline',
                ],
            ],
        ]);

        $data = $response->json('data');
        
        $this->assertCount(3, $data);
        $this->assertEquals('Popular Cave', $data[0]['name']);
        $this->assertEquals(10, $data[0]['total_interactions']);
        $this->assertEquals('Popular Trip', $data[1]['name']);
        $this->assertEquals(8, $data[1]['total_interactions']);
        $this->assertEquals('Less Popular Cave', $data[2]['name']);
        $this->assertEquals(5, $data[2]['total_interactions']);
        
        // Check sparkline is an array of 30 values
        $this->assertCount(30, $data[0]['sparkline']);
        
        // Verify sparkline values are all numeric and non-negative
        foreach ($data[0]['sparkline'] as $value) {
            $this->assertIsInt($value);
            $this->assertGreaterThanOrEqual(0, $value);
        }
        
        // Verify the sum of sparkline values equals total_interactions
        $this->assertEquals($data[0]['total_interactions'], array_sum($data[0]['sparkline']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admin_cannot_access_popular_records()
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        
        $response = $this->getJson('/api/admin/dashboard/popular-records');
        
        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_track_failed_requests()
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        
        $this->assertEquals(0, ApiInteraction::count());
        
        // Request a non-existent cave (should return 404)
        $this->get('/api/caves/non-existent-slug');
        
        // No interaction should be tracked for failed requests
        $this->assertEquals(0, ApiInteraction::count());
    }
}
