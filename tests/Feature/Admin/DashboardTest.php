<?php

namespace Tests\Feature\Admin;

use App\Models\ApiInteraction;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Collection;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_popular_records_with_identifiers()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $system = CaveSystem::create([
            'name' => 'Test System',
            'slug' => 'test-system',
            'length' => 100,
            'vertical_range' => 50
        ]);

        $cave = Cave::create([
            'name' => 'Test Cave',
            'slug' => 'test-cave-slug',
            'cave_system_id' => $system->id,
            'location_name' => 'Test Loc',
            'location_country' => 'Test Country',
            'location_lat' => 0,
            'location_lng' => 0,
            'location_alt' => 0,
        ]);

        $trip = Trip::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
        ]);

        $collection = Collection::create([
            'name' => 'Test Collection',
            'slug' => 'test-collection-slug',
            'user_id' => $admin->id,
        ]);

        $page = \App\Models\Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page-slug',
            'content' => 'Test Content',
            'user_id' => $admin->id,
        ]);

        // Create interactions
        ApiInteraction::create(['trackable_type' => Cave::class, 'trackable_id' => $cave->id, 'created_at' => now()]);
        ApiInteraction::create(['trackable_type' => Trip::class, 'trackable_id' => $trip->id, 'created_at' => now()]);
        ApiInteraction::create(['trackable_type' => Collection::class, 'trackable_id' => $collection->id, 'created_at' => now()]);
        ApiInteraction::create(['trackable_type' => \App\Models\Page::class, 'trackable_id' => $page->id, 'created_at' => now()]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard/popular-records');

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
        
        $data = $response->json('data');
        
        foreach ($data as $record) {
            if ($record['type'] === 'Cave') {
                $this->assertEquals($cave->slug, $record['identifier']);
            } elseif ($record['type'] === 'Trip') {
                $this->assertEquals($trip->short_id, $record['identifier']);
            } elseif ($record['type'] === 'Collection') {
                $this->assertEquals($collection->slug, $record['identifier']);
            } elseif ($record['type'] === 'Page') {
                $this->assertEquals($page->slug, $record['identifier']);
                $this->assertEquals($page->title, $record['name']);
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_page_interactions()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = \App\Models\Page::create([
            'title' => 'Tracking Test Page',
            'slug' => 'tracking-test-page',
            'content' => 'Content',
            'user_id' => $admin->id,
        ]);

        // Hit the public page route
        $response = $this->getJson("/api/pages/{$page->slug}");
        $response->assertOk();

        // Verify interaction was created
        $this->assertDatabaseHas('api_interactions', [
            'trackable_type' => \App\Models\Page::class,
            'trackable_id' => $page->id,
        ]);
    }
}
