<?php

namespace Tests\Feature\Admin;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\Incident;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class IncidentUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_api_returns_region()
    {
        $admin = User::factory()->dutyOfficer()->create();
        
        $cave = Cave::factory()->create();
        // Manual tag creation
        $tag = Tag::factory()->create(['tag' => 'Mendip', 'category' => 'region']);
        $cave->tags()->attach($tag);

        // Manual Callout creation
        $callout = new Callout();
        $callout->user_id = $admin->id;
        $callout->cave_id = $cave->id;
        $callout->callout_time = Carbon::now()->addHour();
        $callout->description = 'Test';
        $callout->car_registration = 'AB12';
        $callout->car_parking = 'Parking';
        $callout->trip_plan = 'Trip Plan';
        $callout->save();

        $incident = Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open'
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/incidents/{$incident->id}");
        
        $response->assertOk();
        $response->assertJsonPath('data.callout.cave.caving_region', 'Mendip');
    }
}
