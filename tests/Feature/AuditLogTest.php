<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Club;
use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\TripUser;
use App\Models\User;
use App\Support\Auditing\StringKeyMorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase; // Add Schema facade

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Config::set('audit.enabled', true);
    }

    public function testCaveAuditLog(): void
    {
        $cave = Cave::factory()->create();
        $cave->update(['name' => 'Updated Cave Name']);
        // Removed incorrect $cave->auditEvent(); call

        // Add a check for the audits table
        $this->assertTrue(Schema::hasTable('audits'), 'Audits table does not exist.');

        $audit = Audit::where('auditable_type', $cave->getMorphClass())
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

        $audit = Audit::where('auditable_type', $trip->getMorphClass())
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

        $audit = Audit::where('auditable_type', $tripMedia->getMorphClass())
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

        $audit = Audit::where('auditable_type', $club->getMorphClass())
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

        $audit = Audit::where('auditable_type', $user->getMorphClass())
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

        $audit = Audit::where('auditable_type', $caveSystem->getMorphClass())
                      ->where('auditable_id', $caveSystem->id)
                      ->where('event', 'updated') // Filter for updated event
                      ->latest()
                      ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Updated Cave System Name', $audit->new_values['name']);
    }

    /**
     * Guards against the Postgres "column \"id\" does not exist" (SQLSTATE
     * 42703) regression on the keyless `trip_user` pivot.
     *
     * The pivot has no `id` column, so TripUser must declare
     * `$incrementing = false`. With Eloquent's default, the insert becomes
     * `insert into "trip_user" (...) returning "id"`, which Postgres rejects
     * outright while SQLite silently satisfies it from the implicit rowid.
     */
    #[Test]
    public function trip_user_pivot_inserts_without_an_id_column(): void
    {
        $this->assertTrue(Schema::hasTable('trip_user'), 'trip_user table does not exist.');
        $this->assertFalse(
            Schema::hasColumn('trip_user', 'id'),
            'trip_user gained an id column — TripUser::$incrementing should be revisited.',
        );

        $trip = Trip::factory()->create();
        $user = User::factory()->create();

        TripUser::factory()->state([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
        ])->create();

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $trip->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Guards against the Postgres "operator does not exist: character varying =
     * integer" (SQLSTATE 42883) regression.
     *
     * auditable_id is a VARCHAR column, so the audits relationship on an
     * integer-keyed model must bind the key as a string. On Postgres a plain
     * integer key produces `whereIntegerInRaw` (`... in (152)`) which fails;
     * SQLite's loose typing hides that, so we assert on the binding type
     * directly to catch a regression on any driver.
     */
    #[Test]
    public function audits_eager_load_binds_auditable_id_as_string(): void
    {
        $trip = Trip::factory()->create();

        $this->assertInstanceOf(StringKeyMorphMany::class, $trip->audits());

        DB::enableQueryLog();
        $trip->load('audits');

        $auditQuery = collect(DB::getQueryLog())
            ->first(fn ($entry): bool => str_contains($entry['query'], 'auditable_id'));

        DB::disableQueryLog();

        $this->assertNotNull($auditQuery, 'No query against the audits table was executed.');
        // A raw integer literal (whereIntegerInRaw) would inline the id and
        // leave no binding — a placeholder plus a string binding is what keeps
        // the comparison valid against the VARCHAR column.
        $this->assertStringContainsString('in (?)', $auditQuery['query']);
        $this->assertContains(
            (string) $trip->id,
            $auditQuery['bindings'],
            'auditable_id should be bound as a string.',
        );
        foreach ($auditQuery['bindings'] as $binding) {
            $this->assertIsString($binding);
        }
    }
}
