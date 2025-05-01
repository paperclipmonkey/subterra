<?php
namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_average_trip_duration_correctly()
    {
        // Arrange: Create a user
        $user = User::factory()->create();

        // Create trips with varying durations
        $trip1 = Trip::factory()->create([
            'start_time' => Carbon::now()->subHours(3),
            'end_time' => Carbon::now()->subHours(1), // 2 hours = 120 minutes
        ]);
        $trip2 = Trip::factory()->create([
            'start_time' => Carbon::now()->subDays(1)->subHours(4),
            'end_time' => Carbon::now()->subDays(1), // 4 hours = 240 minutes
        ]);
        // Trip with null end_time (should be ignored)
        $trip3 = Trip::factory()->create([
            'start_time' => Carbon::now()->subDays(2),
            'end_time' => null,
        ]);
        // Trip with end_time before start_time (should be ignored)
        $trip4 = Trip::factory()->create([
            'start_time' => Carbon::now()->subDays(3),
            'end_time' => Carbon::now()->subDays(3)->subHours(1), // Invalid
        ]);

        // Attach trips to the user
        $user->trips()->attach([$trip1->id, $trip2->id, $trip3->id, $trip4->id]);

        // Act: Call the endpoint
        $response = $this->getJson(route('users.average-trip-duration', ['user' => $user->id]));

        // Assert: Check the response
        $response->assertStatus(200);
        $response->assertJson(['average_duration_minutes' => 180]); // (120 + 240) / 2 = 180
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_zero_average_duration_for_user_with_no_valid_trips()
    {
        // Arrange: Create a user
        $user = User::factory()->create();

        // Trip with null times
        $trip1 = Trip::factory()->create([
            'start_time' => null,
            'end_time' => null,
        ]);
        // Trip with end_time before start_time
        $trip2 = Trip::factory()->create([
            'start_time' => Carbon::now()->subDays(1),
            'end_time' => Carbon::now()->subDays(1)->subHours(1),
        ]);

        $user->trips()->attach([$trip1->id, $trip2->id]);

        // Act: Call the endpoint
        $response = $this->getJson(route('users.average-trip-duration', ['user' => $user->id]));

        // Assert: Check the response
        $response->assertStatus(200);
        $response->assertJson(['average_duration_minutes' => 0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_zero_average_duration_for_user_with_no_trips()
    {
        // Arrange: Create a user with no trips
        $user = User::factory()->create();

        // Act: Call the endpoint
        $response = $this->getJson(route('users.average-trip-duration', ['user' => $user->id]));

        // Assert: Check the response
        $response->assertStatus(200);
        $response->assertJson(['average_duration_minutes' => 0]);
    }
}