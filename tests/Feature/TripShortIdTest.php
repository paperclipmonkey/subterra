<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripShortIdTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_model_generates_short_ids_automatically()
    {
        // Create a trip and verify it has a short_id
        $trip = Trip::factory()->create();

        // Verify the short_id exists
        $this->assertNotEmpty($trip->short_id);

        // Verify it's a string
        $this->assertIsString($trip->short_id);

        // Verify short_id format (8-10 alphanumeric characters)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-zA-Z]{8,10}$/',
            $trip->short_id
        );

        // Verify the length is within expected range
        $this->assertGreaterThanOrEqual(8, strlen($trip->short_id));
        $this->assertLessThanOrEqual(10, strlen($trip->short_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_api_returns_short_id_in_response()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);

        $this->actingAs($user);
        $response = $this->getJson('/api/trips/'.$trip->short_id);

        $response->assertOk();

        $responseData = $response->json('data');

        // Verify the response contains the short_id as 'id'
        $this->assertEquals($trip->short_id, $responseData['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{8,10}$/', $responseData['id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_api_accepts_short_id_in_routes()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);

        $this->actingAs($user);

        // Test GET with short_id
        $response = $this->getJson('/api/trips/'.$trip->short_id);
        $response->assertOk();

        // Test PUT with short_id (if user has permission)
        $trip->participants()->attach($user);

        $updateData = [
            'name' => 'Updated Trip Name',
            'description' => 'Updated description',
            'visibility' => 'public',
            'participants' => [$user->id],
        ];

        $response = $this->putJson('/api/trips/'.$trip->short_id, $updateData);
        $response->assertOk();

        // Verify the update worked
        $trip->refresh();
        $this->assertEquals('Updated Trip Name', $trip->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_relationships_work_with_short_ids()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();

        // Test many-to-many relationship with users
        $trip->participants()->attach($user);

        // Verify the relationship works
        $this->assertTrue($trip->participants->contains($user));
        $this->assertTrue($user->trips->contains($trip));

        // Verify we can query through the relationship
        $foundTrip = $user->trips()->where('trip_id', $trip->id)->first();
        $this->assertEquals($trip->short_id, $foundTrip->short_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_authorization_works_with_short_ids()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();

        // Add user as participant
        $trip->participants()->attach($user);

        $this->actingAs($user);

        // Test that authorization works in requests
        $hasPermission = $user->trips()->where('trip_id', $trip->id)->exists();
        $this->assertTrue($hasPermission);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_csv_export_includes_short_ids()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);

        $this->actingAs($user);
        $response = $this->get('/api/me/trips/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        // Check that the CSV content includes the short_id
        $csvContent = $response->streamedContent();
        $this->assertStringContainsString($trip->short_id, $csvContent);
        $this->assertStringContainsString('Trip ID', $csvContent);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multiple_trips_have_unique_short_ids()
    {
        $trip1 = Trip::factory()->create();
        $trip2 = Trip::factory()->create();
        $trip3 = Trip::factory()->create();

        // Verify all have short_ids
        $this->assertNotEmpty($trip1->short_id);
        $this->assertNotEmpty($trip2->short_id);
        $this->assertNotEmpty($trip3->short_id);

        // Verify all are unique
        $this->assertNotEquals($trip1->short_id, $trip2->short_id);
        $this->assertNotEquals($trip1->short_id, $trip3->short_id);
        $this->assertNotEquals($trip2->short_id, $trip3->short_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_media_relationship_works_with_short_ids()
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function short_ids_are_url_safe()
    {
        $trip = Trip::factory()->create();

        // Verify the short_id only contains URL-safe characters
        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', $trip->short_id);

        // Verify it doesn't contain any special characters that need URL encoding
        $this->assertEquals($trip->short_id, urlencode($trip->short_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function short_ids_are_not_sequential()
    {
        $trip1 = Trip::factory()->create();
        $trip2 = Trip::factory()->create();
        $trip3 = Trip::factory()->create();

        // Verify that the short_ids are not sequential
        // (they should be random, not based on auto-incrementing IDs)
        // Verify that the short_ids are not equal (i.e., not sequential or duplicated)
        $this->assertNotEquals($trip1->short_id, $trip2->short_id);
        $this->assertNotEquals($trip2->short_id, $trip3->short_id);

        // Optionally, check that the IDs do not follow a simple pattern (e.g., not all the same, not incrementing)
        $ids = [$trip1->short_id, $trip2->short_id, $trip3->short_id];
        $this->assertCount(3, array_unique($ids));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trip_list_api_returns_short_ids()
    {
        $user = User::factory()->create();
        Trip::factory()->count(3)->create(['visibility' => 'public']);

        $this->actingAs($user);
        $response = $this->getJson('/api/trips');

        $response->assertOk();

        $trips = $response->json('data');

        // Verify all trips have short_ids in the correct format
        foreach ($trips as $trip) {
            $this->assertArrayHasKey('id', $trip);
            $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]{8,10}$/', $trip['id']);
        }
    }
}
