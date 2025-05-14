<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Cave;
use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\Club;
use App\Models\User;
use App\Models\CaveSystem;
use App\Models\TripUser;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema; // Add Schema facade

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function testCaveAuditLog(): void
    {
        $cave = Cave::factory()->create();
        $cave->update(['name' => 'Updated Cave Name']);
        // Removed incorrect $cave->auditEvent(); call

        // Add a check for the audits table
        $this->assertTrue(Schema::hasTable('audits'), 'Audits table does not exist.');

        $audit = Audit::where('auditable_type', Cave::class)
                      ->where('auditable_id', $cave->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit, 'Audit record not found for Cave update.');
        $this->assertEquals('Updated Cave Name', $audit->new_values['name']);
    }

    public function testTripAuditLog(): void
    {
        $trip = Trip::factory()->create();
        $trip->update(['name' => 'Updated Trip Name']);

        $audit = Audit::where('auditable_type', Trip::class)
                      ->where('auditable_id', $trip->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Trip Name', $audit->new_values['name']);
    }

    public function testTripMediaAuditLog(): void
    {
        $trip = Trip::factory()->create(); // Create a trip first
        // Explicitly set trip_id using state
        $tripMedia = TripMedia::factory()->state(['trip_id' => $trip->id])->create();
        $tripMedia->update(['filename' => 'updated_filename.jpg']);

        // Add a check for the audits table
        $this->assertTrue(Schema::hasTable('audits'), 'Audits table does not exist.');

        $audit = Audit::where('auditable_type', TripMedia::class)
                      ->where('auditable_id', $tripMedia->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit, 'Audit record not found for TripMedia update.');
        $this->assertEquals('updated_filename.jpg', $audit->new_values['filename']);
    }

    public function testClubAuditLog(): void
    {
        $club = Club::factory()->create();
        $club->update(['name' => 'Updated Club Name']);

        $audit = Audit::where('auditable_type', Club::class)
                      ->where('auditable_id', $club->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Club Name', $audit->new_values['name']);
    }

    public function testUserAuditLog(): void
    {
        $user = User::factory()->create();
        $user->update(['name' => 'Updated User Name']);

        $audit = Audit::where('auditable_type', User::class)
                      ->where('auditable_id', $user->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated User Name', $audit->new_values['name']);
    }

    public function testCaveSystemAuditLog(): void
    {
        $caveSystem = CaveSystem::factory()->create();
        $caveSystem->update(['name' => 'Updated Cave System Name']);

        $audit = Audit::where('auditable_type', CaveSystem::class)
                      ->where('auditable_id', $caveSystem->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Cave System Name', $audit->new_values['name']);
    }

    public function testTripUserAuditLog(): void
    {
        // Add a check for the trip_user table
        $this->assertTrue(Schema::hasTable('trip_user'), 'trip_user table does not exist.');

        $trip = Trip::factory()->create(); // Create a trip first
        $user = User::factory()->create(); // Create a user first
        // Explicitly set trip_id and user_id using state
        $tripUser = TripUser::factory()->state([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
        ])->create();

        $newTrip = Trip::factory()->create(); // Create another trip for update
        $tripUser->update(['trip_id' => $newTrip->id]);

        $audit = Audit::where('auditable_type', TripUser::class)
                      ->where('auditable_id', $tripUser->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit, 'Audit record not found for TripUser update.');
        $this->assertEquals($newTrip->id, $audit->new_values['trip_id']);
    }
}
