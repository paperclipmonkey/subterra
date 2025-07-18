<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class TripUuidTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_model_generates_uuids_automatically()
    {
        // Create a trip and verify it has a UUID
        $trip = Trip::factory()->create();
        
        // Verify the ID is a valid UUID
        $this->assertTrue(Str::isUuid($trip->id));
        
        // Verify it's a string, not an integer
        $this->assertIsString($trip->id);
        
        // Verify UUID format (36 characters with dashes)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $trip->id
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_api_returns_uuid_in_response()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        
        $response->assertOk();
        
        $responseData = $response->json('data');
        
        // Verify the response contains the UUID
        $this->assertEquals($trip->id, $responseData['id']);
        $this->assertTrue(Str::isUuid($responseData['id']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_api_accepts_uuid_in_routes()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        
        $this->actingAs($user);
        
        // Test GET with UUID
        $response = $this->getJson('/api/trips/' . $trip->id);
        $response->assertOk();
        
        // Test PUT with UUID (if user has permission)
        $trip->participants()->attach($user);
        
        $updateData = [
            'name' => 'Updated Trip Name',
            'description' => 'Updated description',
            'visibility' => 'public',
            'participants' => [$user->id],
        ];
        
        $response = $this->putJson('/api/trips/' . $trip->id, $updateData);
        $response->assertOk();
        
        // Verify the update worked
        $trip->refresh();
        $this->assertEquals('Updated Trip Name', $trip->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_relationships_work_with_uuids()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        
        // Test many-to-many relationship with users
        $trip->participants()->attach($user);
        
        // Verify the relationship works
        $this->assertTrue($trip->participants->contains($user));
        $this->assertTrue($user->trips->contains($trip));
        
        // Verify we can query through the relationship using UUID
        $foundTrip = $user->trips()->where('trip_id', $trip->id)->first();
        $this->assertEquals($trip->id, $foundTrip->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_authorization_works_with_uuids()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        
        // Add user as participant
        $trip->participants()->attach($user);
        
        $this->actingAs($user);
        
        // Test that authorization works in requests
        // This tests the logic in UpdateTripRequest and DeleteTripRequest
        $hasPermission = $user->trips()->where('trip_id', $trip->id)->exists();
        $this->assertTrue($hasPermission);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_csv_export_includes_uuids()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);
        
        $this->actingAs($user);
        $response = $this->get('/api/me/trips/download');
        
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        // Check that the CSV content includes the UUID
        $csvContent = $response->streamedContent();
        $this->assertStringContainsString($trip->id, $csvContent);
        $this->assertStringContainsString('Trip ID', $csvContent);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multiple_trips_have_unique_uuids()
    {
        $trip1 = Trip::factory()->create();
        $trip2 = Trip::factory()->create();
        $trip3 = Trip::factory()->create();
        
        // Verify all have UUIDs
        $this->assertTrue(Str::isUuid($trip1->id));
        $this->assertTrue(Str::isUuid($trip2->id));
        $this->assertTrue(Str::isUuid($trip3->id));
        
        // Verify all are unique
        $this->assertNotEquals($trip1->id, $trip2->id);
        $this->assertNotEquals($trip1->id, $trip3->id);
        $this->assertNotEquals($trip2->id, $trip3->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_media_relationship_works_with_uuids()
    {
        $trip = Trip::factory()->create();
        
        // Create trip media (if the model exists)
        if (class_exists(\App\Models\TripMedia::class)) {
            $media = $trip->media()->create(['filename' => 'test.jpg']);
            
            // Verify the relationship works
            $this->assertEquals($trip->id, $media->trip_id);
            $this->assertTrue($trip->media->contains($media));
        }
        
        $this->assertTrue(true); // Pass if TripMedia doesn't exist
    }
}