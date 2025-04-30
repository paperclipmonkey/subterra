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

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function testCaveAuditLog(): void
    {
        $cave = Cave::factory()->create();
        $cave->update(['name' => 'Updated Cave Name']);

        $audit = Audit::where('auditable_type', Cave::class)->where('auditable_id', $cave->id)->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Cave Name', $audit->new_values['name']);
    }

    public function testTripAuditLog(): void
    {
        $trip = Trip::factory()->create();
        $trip->update(['name' => 'Updated Trip Name']);

        $audit = Audit::where('auditable_type', Trip::class)->where('auditable_id', $trip->id)->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Trip Name', $audit->new_values['name']);
    }

    public function testTripMediaAuditLog(): void
    {
        $tripMedia = TripMedia::factory()->create();
        $tripMedia->update(['filename' => 'updated_filename.jpg']);

        $audit = Audit::where('auditable_type', TripMedia::class)->where('auditable_id', $tripMedia->id)->first();

        $this->assertNotNull($audit);
        $this->assertEquals('updated_filename.jpg', $audit->new_values['filename']);
    }

    public function testClubAuditLog(): void
    {
        $club = Club::factory()->create();
        $club->update(['name' => 'Updated Club Name']);

        $audit = Audit::where('auditable_type', Club::class)->where('auditable_id', $club->id)->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Club Name', $audit->new_values['name']);
    }

    public function testUserAuditLog(): void
    {
        $user = User::factory()->create();
        $user->update(['name' => 'Updated User Name']);

        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $user->id)->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated User Name', $audit->new_values['name']);
    }

    public function testCaveSystemAuditLog(): void
    {
        $caveSystem = CaveSystem::factory()->create();
        $caveSystem->update(['name' => 'Updated Cave System Name']);

        $audit = Audit::where('auditable_type', CaveSystem::class)->where('auditable_id', $caveSystem->id)->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Cave System Name', $audit->new_values['name']);
    }

    public function testTripUserAuditLog(): void
    {
        $tripUser = TripUser::factory()->create();
        $tripUser->update(['trip_id' => 999]);

        $audit = Audit::where('auditable_type', TripUser::class)->where('auditable_id', $tripUser->id)->first();

        $this->assertNotNull($audit);
        $this->assertEquals(999, $audit->new_values['trip_id']);
    }
}
