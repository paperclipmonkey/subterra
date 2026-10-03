<?php

declare(strict_types=1);

namespace Tests\Feature\TripImport;

use App\Events\TripCreated;
use App\Events\TripParticipantTagged;
use App\Events\UserContributed;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Club;
use App\Models\Tag;
use App\Models\Trip;
use App\Models\TripImport;
use App\Models\TripImportRow;
use App\Models\User;
use App\Services\TripImport\TripImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private TripImportService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(TripImportService::class);
        $this->user = User::factory()->create(['name' => 'Pat Importer']);
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function system(string $name, array $entrances, array $systemAttrs = []): CaveSystem
    {
        $system = CaveSystem::factory()->create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)] + $systemAttrs);
        foreach ($entrances as $entrance) {
            Cave::factory()->create([
                'name' => $entrance,
                'slug' => \Illuminate\Support\Str::slug($entrance),
                'cave_system_id' => $system->id,
            ]);
        }

        return $system;
    }

    /** @param array<string, mixed> $overrides */
    private function stage(array $overrides = []): TripImportRow
    {
        return $this->stageMany([$overrides])[0];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return TripImportRow[]
     */
    private function stageMany(array $rows): array
    {
        $import = $this->service->openOrCreate($this->user);
        $result = $this->service->addRows($import, $this->user, array_map(fn ($r) => $r + [
            'cave_name' => 'Swildon\'s Hole',
            'date' => '2024-06-14',
            'companions' => [],
        ], $rows), 'file');

        return TripImportRow::whereIn('row_number', $result['row_numbers'])
            ->where('trip_import_id', $import->id)
            ->orderBy('row_number')
            ->get()
            ->all();
    }

    private function codes(TripImportRow $row): array
    {
        return array_column($row->fresh()->issues ?? [], 'code');
    }

    private function import(): TripImport
    {
        return $this->service->openImportFor($this->user);
    }

    // -------------------------------------------------------------------------
    // Cave matching
    // -------------------------------------------------------------------------

    #[Test]
    public function exact_single_entrance_cave_is_ready(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $row = $this->stage(['cave_name' => 'swildons hole']);

        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
        $this->assertNotNull($row->entrance_cave_id);
    }

    #[Test]
    public function curly_apostrophes_and_extra_spaces_still_match_exactly(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $row = $this->stage(['cave_name' => '  Swildon’s   Hole ']);

        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
    }

    #[Test]
    public function an_entrance_name_matches_its_system(): void
    {
        $system = $this->system('Gaping Gill', ['Bar Pot', 'Main Shaft', 'Flood Entrance']);

        $row = $this->stage(['cave_name' => 'Bar Pot']);

        $this->assertSame($system->id, $row->cave_system_id);
        $this->assertSame('Bar Pot', $row->entrance->name);
        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
    }

    #[Test]
    public function multi_entrance_system_without_an_entrance_needs_review(): void
    {
        $this->system('Gaping Gill', ['Bar Pot', 'Main Shaft']);

        $row = $this->stage(['cave_name' => 'Gaping Gill']);

        $this->assertSame(TripImportRow::STATUS_NEEDS_REVIEW, $row->status);
        $this->assertSame(['entrance_needed'], $this->codes($row));
    }

    #[Test]
    public function the_entrance_column_picks_the_entrance(): void
    {
        $this->system('Gaping Gill', ['Bar Pot', 'Main Shaft']);

        $row = $this->stage(['cave_name' => 'Gaping Gill', 'entrance_name' => 'bar pot']);

        $this->assertSame('Bar Pot', $row->entrance->name);
        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
    }

    #[Test]
    public function an_entrance_named_after_the_system_is_the_default(): void
    {
        $this->system('Lancaster Hole', ['Lancaster Hole', 'Wretched Rabbit']);

        $row = $this->stage(['cave_name' => 'Lancaster Hole']);

        $this->assertSame('Lancaster Hole', $row->entrance->name);
    }

    #[Test]
    public function through_trip_written_as_x_to_y_sets_entrance_and_exit(): void
    {
        $this->system('Easegill System', ['Lancaster Hole', 'County Pot']);

        $row = $this->stage(['cave_name' => 'Lancaster Hole to County Pot']);

        $this->assertSame('Lancaster Hole', $row->entrance->name);
        $this->assertSame('County Pot', $row->exit->name);
        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
    }

    #[Test]
    public function bracketed_entrance_is_understood(): void
    {
        $this->system('Gaping Gill', ['Bar Pot', 'Main Shaft']);

        $row = $this->stage(['cave_name' => 'Gaping Gill (Bar Pot)']);

        $this->assertSame('Bar Pot', $row->entrance->name);
    }

    #[Test]
    public function an_unknown_cave_needs_review_as_not_found(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $row = $this->stage(['cave_name' => 'Nonexistent Pot']);

        $this->assertSame(TripImportRow::STATUS_NEEDS_REVIEW, $row->status);
        $this->assertSame(['cave_not_found'], $this->codes($row));
    }

    #[Test]
    public function a_partial_name_is_suggested_but_never_matched_automatically(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $row = $this->stage(['cave_name' => 'Swildons']);

        $this->assertNull($row->cave_system_id);
        $this->assertSame(['cave_ambiguous'], $this->codes($row));
        $this->assertSame('swildons-hole', $row->cave_candidates[0]['cave_system_slug']);
    }

    #[Test]
    public function the_same_name_in_two_systems_is_ambiguous(): void
    {
        foreach (['Giants Hole Peak', 'Giants Hole Mendip'] as $name) {
            Cave::factory()->create(['name' => 'Giant\'s Hole', 'cave_system_id' => $this->system($name, [])->id]);
        }

        $row = $this->stage(['cave_name' => 'Giant\'s Hole']);

        $this->assertSame(['cave_ambiguous'], $this->codes($row));
        $this->assertCount(2, $row->cave_candidates);
    }

    #[Test]
    public function an_admin_only_cave_is_never_matched(): void
    {
        $system = $this->system('Secret Dig', []);
        Cave::factory()->create(['name' => 'Secret Dig', 'cave_system_id' => $system->id, 'visibility' => 'admin_only']);

        $row = $this->stage(['cave_name' => 'Secret Dig']);

        $this->assertNull($row->cave_system_id);
        $this->assertSame(['cave_not_found'], $this->codes($row));
    }

    #[Test]
    public function a_missing_cave_needs_review(): void
    {
        $row = $this->stage(['cave_name' => '']);

        $this->assertSame(['cave_missing'], $this->codes($row));
    }

    #[Test]
    public function resolving_a_cave_name_fixes_every_row_with_it(): void
    {
        $system = $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $rows = $this->stageMany([
            ['cave_name' => 'Swilly', 'date' => '2024-01-01'],
            ['cave_name' => 'swilly', 'date' => '2024-02-01'],
            ['cave_name' => 'Other', 'date' => '2024-03-01'],
        ]);

        $result = $this->service->resolveCave($this->import(), $this->user, 'Swilly', null, 'swildons-hole');

        $this->assertSame(2, $result['rows_updated']);
        $this->assertSame($system->id, $rows[0]->fresh()->cave_system_id);
        $this->assertSame(TripImportRow::STATUS_READY, $rows[1]->fresh()->status);
        $this->assertNull($rows[2]->fresh()->cave_system_id);
    }

    #[Test]
    public function resolving_to_an_entrance_of_another_system_is_refused(): void
    {
        $this->system('Gaping Gill', ['Bar Pot', 'Main Shaft']);
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $this->stage(['cave_name' => 'GG']);

        $result = $this->service->resolveCave($this->import(), $this->user, 'GG', null, 'gaping-gill', 'swildons-hole');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(['bar-pot', 'main-shaft'], array_column($result['system']['entrances'], 'slug'));
    }

    #[Test]
    public function resolving_a_multi_entrance_system_reports_rows_still_needing_an_entrance(): void
    {
        $this->system('Gaping Gill', ['Bar Pot', 'Main Shaft']);
        $this->stage(['cave_name' => 'GG']);

        $result = $this->service->resolveCave($this->import(), $this->user, 'GG', null, 'gaping-gill');

        $this->assertSame([1], $result['rows_needing_entrance']);
        $this->assertCount(2, $result['entrances']);
    }

    #[Test]
    public function resolving_an_unknown_system_is_refused(): void
    {
        $this->stage(['cave_name' => 'GG']);

        $result = $this->service->resolveCave($this->import(), $this->user, 'GG', null, 'no-such-system');

        $this->assertArrayHasKey('error', $result);
    }

    // -------------------------------------------------------------------------
    // Participants
    // -------------------------------------------------------------------------

    #[Test]
    public function an_exact_name_match_is_tagged(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $alice = User::factory()->create(['name' => 'Alice Smith']);

        $row = $this->stage(['companions' => ['alice smith']]);

        $this->assertSame($alice->id, $row->participants[0]['user_id']);
        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
    }

    #[Test]
    public function a_first_name_only_match_needs_confirming(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        User::factory()->create(['name' => 'Bob Jones']);
        User::factory()->create(['name' => 'Bob Smith']);

        $row = $this->stage(['companions' => ['Bob']]);

        $this->assertSame(TripImportRow::STATUS_NEEDS_REVIEW, $row->status);
        $this->assertSame(['participant_ambiguous'], $this->codes($row));
        $this->assertCount(2, $row->participants[0]['candidates']);
    }

    #[Test]
    public function someone_not_on_subterra_is_a_guest_and_does_not_block(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $row = $this->stage(['companions' => ['Zebedee Unknown']]);

        $this->assertSame('guest', $row->participants[0]['status']);
        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
    }

    #[Test]
    public function the_importer_and_solo_markers_are_dropped(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $row = $this->stage(['companions' => ['Pat Importer', 'me', 'solo']]);

        $this->assertSame([], $row->participants);
    }

    #[Test]
    public function a_club_only_user_outside_the_importers_clubs_is_not_matched(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        User::factory()->create(['name' => 'Private Person', 'visibility_addable' => 'club']);

        $row = $this->stage(['companions' => ['Private Person']]);

        $this->assertSame('guest', $row->participants[0]['status']);
    }

    #[Test]
    public function a_club_mate_with_club_only_tagging_is_matched(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $club = Club::factory()->create();
        $mate = User::factory()->create(['name' => 'Club Mate', 'visibility_addable' => 'club']);
        DB::table('club_user')->insert([
            ['user_id' => $this->user->id, 'club_id' => $club->id, 'status' => 'approved'],
            ['user_id' => $mate->id, 'club_id' => $club->id, 'status' => 'approved'],
        ]);

        $row = $this->stage(['companions' => ['Club Mate']]);

        $this->assertSame($mate->id, $row->participants[0]['user_id']);
    }

    #[Test]
    public function resolving_a_person_applies_to_every_row(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $bob = User::factory()->create(['name' => 'Bob Jones']);
        User::factory()->create(['name' => 'Bob Smith']);
        $rows = $this->stageMany([
            ['companions' => ['Bob'], 'date' => '2024-01-01'],
            ['companions' => ['Bob'], 'date' => '2024-02-01'],
        ]);

        $result = $this->service->resolvePerson($this->import(), $this->user, 'Bob', 'tag', $bob->id);

        $this->assertSame(2, $result['rows_updated']);
        foreach ($rows as $row) {
            $this->assertSame($bob->id, $row->fresh()->participants[0]['user_id']);
            $this->assertSame(TripImportRow::STATUS_READY, $row->fresh()->status);
        }
    }

    #[Test]
    public function tagging_someone_the_importer_may_not_tag_is_refused(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        User::factory()->create(['name' => 'Bob Jones']);
        User::factory()->create(['name' => 'Bob Smith']);
        $private = User::factory()->create(['name' => 'Bob Private', 'visibility_addable' => 'club']);
        $this->stage(['companions' => ['Bob']]);

        $result = $this->service->resolvePerson($this->import(), $this->user, 'Bob', 'tag', $private->id);

        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function a_person_can_be_made_a_guest_or_removed(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        User::factory()->create(['name' => 'Bob Jones']);
        User::factory()->create(['name' => 'Bob Smith']);
        $row = $this->stage(['companions' => ['Bob', 'Al']]);
        User::factory()->create(['name' => 'Al Ambiguous']);

        $this->service->resolvePerson($this->import(), $this->user, 'Bob', 'guest');
        $this->service->resolvePerson($this->import(), $this->user, 'Al', 'remove');

        $this->assertSame([['name' => 'Bob', 'user_id' => null, 'status' => 'guest']], $row->fresh()->participants);
    }

    // -------------------------------------------------------------------------
    // Dates, durations, duplicates, closed caves
    // -------------------------------------------------------------------------

    #[Test]
    public function date_problems_need_review(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $rows = $this->stageMany([
            ['date' => null, 'date_raw' => 'sometime'],
            ['date' => null],
            ['date' => now()->addMonth()->format('Y-m-d')],
            ['date' => '1850-01-01'],
        ]);

        $this->assertSame(['date_unreadable'], $this->codes($rows[0]));
        $this->assertSame(['date_missing'], $this->codes($rows[1]));
        $this->assertSame(['date_in_future'], $this->codes($rows[2]));
        $this->assertSame(['date_implausible'], $this->codes($rows[3]));
    }

    #[Test]
    public function a_very_long_duration_is_a_warning_not_a_blocker(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $row = $this->stage(['duration_minutes' => 60 * 60]);

        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
        $this->assertSame(['duration_unusual'], $this->codes($row));
    }

    #[Test]
    public function an_existing_trip_on_the_same_day_is_a_duplicate(): void
    {
        $system = $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $trip = Trip::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $system->caves()->first()->id,
            'exit_cave_id' => $system->caves()->first()->id,
            'start_time' => Carbon::parse('2024-06-14 22:30', 'Europe/London')->utc(),
        ]);
        $trip->participants()->attach($this->user->id);

        $row = $this->stage();

        $this->assertSame(TripImportRow::STATUS_DUPLICATE, $row->status);

        $this->service->updateRows($this->import(), $this->user, [$row->row_number], ['allow_duplicate' => true]);
        $this->assertSame(TripImportRow::STATUS_READY, $row->fresh()->status);
    }

    #[Test]
    public function someone_elses_trip_is_not_a_duplicate(): void
    {
        $system = $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $trip = Trip::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $system->caves()->first()->id,
            'exit_cave_id' => $system->caves()->first()->id,
            'start_time' => Carbon::parse('2024-06-14 12:00', 'Europe/London')->utc(),
        ]);
        $trip->participants()->attach(User::factory()->create()->id);

        $this->assertSame(TripImportRow::STATUS_READY, $this->stage()->status);
    }

    #[Test]
    public function the_same_trip_twice_in_one_file_is_a_duplicate(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);

        $rows = $this->stageMany([[], []]);

        $this->assertSame(TripImportRow::STATUS_READY, $rows[0]->status);
        $this->assertSame(TripImportRow::STATUS_DUPLICATE, $rows[1]->status);
    }

    #[Test]
    public function a_closed_cave_warns_and_is_imported_privately(): void
    {
        Event::fake([UserContributed::class]);
        $system = $this->system('Closed Cave', ['Closed Cave']);
        $tag = Tag::factory()->create(['tag' => 'Closed', 'type' => 'cave', 'category' => 'access']);
        $system->caves()->first()->tags()->attach($tag);

        $row = $this->stage(['cave_name' => 'Closed Cave']);
        $this->assertSame(TripImportRow::STATUS_READY, $row->status);
        $this->assertSame(['closed_cave'], $this->codes($row));

        $this->service->commit($this->import(), $this->user);

        $this->assertSame('private', $row->fresh()->trip->visibility);
    }

    // -------------------------------------------------------------------------
    // Editing rows
    // -------------------------------------------------------------------------

    #[Test]
    public function rows_can_be_corrected_skipped_and_unskipped(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $row = $this->stage(['date' => null]);

        $this->service->updateRows($this->import(), $this->user, [$row->row_number], ['date' => '2023-05-01', 'start_time' => '10:30']);
        $this->assertSame(TripImportRow::STATUS_READY, $row->fresh()->status);
        $this->assertSame('10:30', $row->fresh()->start_time);

        $this->service->updateRows($this->import(), $this->user, [$row->row_number], ['skip' => true]);
        $this->assertSame(TripImportRow::STATUS_SKIPPED, $row->fresh()->status);

        $this->service->updateRows($this->import(), $this->user, [$row->row_number], ['skip' => false]);
        $this->assertSame(TripImportRow::STATUS_READY, $row->fresh()->status);
    }

    #[Test]
    public function invalid_corrections_are_refused(): void
    {
        $row = $this->stage();
        $import = $this->import();

        $this->assertArrayHasKey('error', $this->service->updateRows($import, $this->user, [$row->row_number], ['date' => 'whenever']));
        $this->assertArrayHasKey('error', $this->service->updateRows($import, $this->user, [$row->row_number], ['start_time' => 'noonish']));
        $this->assertArrayHasKey('error', $this->service->updateRows($import, $this->user, [$row->row_number], ['visibility' => 'everyone']));
        $this->assertArrayHasKey('error', $this->service->updateRows($import, $this->user, [999], ['skip' => true]));
    }

    #[Test]
    public function an_import_holds_a_limited_number_of_rows(): void
    {
        config(['assistant.import.max_rows' => 2]);
        $import = $this->service->openOrCreate($this->user);

        $result = $this->service->addRows($import, $this->user, array_fill(0, 3, ['cave_name' => 'X', 'date' => '2024-01-01']), 'file');

        $this->assertCount(2, $result['row_numbers']);
        $this->assertSame(1, $result['rejected']);
    }

    // -------------------------------------------------------------------------
    // Committing
    // -------------------------------------------------------------------------

    #[Test]
    public function commit_creates_trips_without_per_trip_notifications(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class, UserContributed::class]);
        $system = $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $alice = User::factory()->create(['name' => 'Alice Smith']);

        $row = $this->stage([
            'companions' => ['Alice Smith', 'Zebedee Unknown'],
            'start_time' => '10:00',
            'duration_minutes' => 150,
            'description' => 'Down to Sump 1.',
        ]);

        $result = $this->service->commit($this->import(), $this->user);

        $this->assertSame(1, $result['imported']);
        $trip = $row->fresh()->trip;
        $this->assertSame($system->id, $trip->cave_system_id);
        $this->assertSame('Swildon\'s Hole — 14 Jun 2024', $trip->name);
        $this->assertSame('public', $trip->visibility);
        // 10:00 BST is 09:00 UTC
        $this->assertSame('2024-06-14 09:00:00', $trip->start_time->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(150, $trip->duration);
        $this->assertStringContainsString('Down to Sump 1.', $trip->description);
        $this->assertStringContainsString('Zebedee Unknown', $trip->description);
        $this->assertEqualsCanonicalizing([$this->user->id, $alice->id], $trip->participants->pluck('id')->all());

        Event::assertNotDispatched(TripCreated::class);
        Event::assertNotDispatched(TripParticipantTagged::class);
        Event::assertDispatched(UserContributed::class, fn ($e) => $e->user->id === $alice->id);
        Event::assertDispatched(UserContributed::class, fn ($e) => $e->user->id === $this->user->id);

        $this->assertSame(TripImport::STATUS_COMPLETED, TripImport::first()->status);
        $this->assertNull($this->service->openImportFor($this->user));
    }

    #[Test]
    public function commit_only_imports_ready_rows_and_keeps_the_import_open(): void
    {
        Event::fake([UserContributed::class]);
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $rows = $this->stageMany([
            ['date' => '2024-01-01'],
            ['cave_name' => 'Nowhere', 'date' => '2024-02-01'],
        ]);

        $result = $this->service->commit($this->import(), $this->user);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(TripImportRow::STATUS_IMPORTED, $rows[0]->fresh()->status);
        $this->assertSame(TripImportRow::STATUS_NEEDS_REVIEW, $rows[1]->fresh()->status);
        $this->assertNotNull($this->service->openImportFor($this->user));
        $this->assertSame(1, Trip::count());
    }

    #[Test]
    public function commit_rechecks_for_duplicates_created_since_staging(): void
    {
        Event::fake([UserContributed::class]);
        $system = $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $row = $this->stage();
        $this->assertSame(TripImportRow::STATUS_READY, $row->status);

        $trip = Trip::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $system->caves()->first()->id,
            'exit_cave_id' => $system->caves()->first()->id,
            'start_time' => Carbon::parse('2024-06-14 12:00', 'Europe/London')->utc(),
        ]);
        $trip->participants()->attach($this->user->id);

        $result = $this->service->commit($this->import(), $this->user);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(TripImportRow::STATUS_DUPLICATE, $row->fresh()->status);
    }

    #[Test]
    public function commit_turns_a_companion_who_can_no_longer_be_tagged_into_a_guest(): void
    {
        Event::fake([UserContributed::class]);
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $alice = User::factory()->create(['name' => 'Alice Smith']);
        $row = $this->stage(['companions' => ['Alice Smith']]);

        $alice->update(['visibility_addable' => 'club']);
        $this->service->commit($this->import(), $this->user);

        $trip = $row->fresh()->trip;
        $this->assertSame([$this->user->id], $trip->participants->pluck('id')->all());
        $this->assertStringContainsString('Alice Smith', $trip->description);
    }

    #[Test]
    public function commit_respects_the_per_call_limit(): void
    {
        Event::fake([UserContributed::class]);
        config(['assistant.import.max_commit_per_call' => 2]);
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $this->stageMany([['date' => '2024-01-01'], ['date' => '2024-01-02'], ['date' => '2024-01-03']]);

        $result = $this->service->commit($this->import(), $this->user);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['remaining']['ready']);
        $this->assertNotNull($result['more_ready']);
    }

    #[Test]
    public function discarding_keeps_imported_trips(): void
    {
        Event::fake([UserContributed::class]);
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        $rows = $this->stageMany([['date' => '2024-01-01'], ['cave_name' => 'Nowhere']]);
        $this->service->commit($this->import(), $this->user, [1]);

        $result = $this->service->discard($this->import());

        $this->assertSame(1, $result['rows_discarded']);
        $this->assertSame(1, Trip::count());
        $this->assertNull($this->service->openImportFor($this->user));
        $this->assertSame(TripImportRow::STATUS_SKIPPED, $rows[1]->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    #[Test]
    public function the_summary_groups_problems_so_one_answer_covers_many_rows(): void
    {
        $this->system('Swildon\'s Hole', ['Swildon\'s Hole']);
        User::factory()->create(['name' => 'Bob Jones']);
        User::factory()->create(['name' => 'Bob Smith']);
        $this->stageMany([
            ['cave_name' => 'Swildons', 'date' => '2024-01-01'],
            ['cave_name' => 'Swildons', 'date' => '2024-02-01'],
            ['cave_name' => 'Swildon\'s Hole', 'date' => '2024-03-01', 'companions' => ['Bob', 'Zed Guest']],
        ]);

        $summary = $this->service->summary($this->import());

        $this->assertSame(3, $summary['counts']['total']);
        $this->assertCount(1, $summary['caves_to_resolve']);
        $this->assertSame(2, $summary['caves_to_resolve'][0]['rows']);
        $this->assertSame('cave_ambiguous', $summary['caves_to_resolve'][0]['problem']);
        $this->assertSame('Bob', $summary['people_to_resolve'][0]['name']);
        $this->assertSame('Zed Guest', $summary['guests'][0]['name']);
    }

    #[Test]
    public function old_imports_are_pruned(): void
    {
        $this->stage();
        $old = $this->import();
        TripImport::whereKey($old->id)->update(['updated_at' => now()->subDays(TripImport::RETENTION_DAYS + 1)]);

        $this->artisan('model:prune', ['--model' => [TripImport::class]])->assertSuccessful();

        $this->assertSame(0, TripImport::count());
        $this->assertSame(0, TripImportRow::count());
    }
}
