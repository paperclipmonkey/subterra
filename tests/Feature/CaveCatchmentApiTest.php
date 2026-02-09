<?php

namespace Tests\Feature;

use App\Models\Catchment;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaveCatchmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cave_api_returns_catchment_name()
    {
        \App\Models\Tag::factory()->create(['tag' => 'Previously Done', 'category' => 'status']);
        \App\Models\Tag::factory()->create(['tag' => 'Not Done Yet', 'category' => 'status']);
        
        $user = User::factory()->create(['is_approved' => true]);
        $catchment = Catchment::factory()->create(['name' => 'Test Catchment']);
        $system = CaveSystem::factory()->create(['catchment_id' => $catchment->id]);
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $response = $this->actingAs($user)->getJson("/api/caves/{$cave->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.system.catchment_name', 'Test Catchment');
    }
}
