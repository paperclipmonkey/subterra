<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Callout;
use App\Models\CalloutParticipant;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Club;
use App\Models\Collection;
use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\Medal;
use App\Models\OnCallShift;
use App\Models\Page;
use App\Models\Permit;
use App\Models\PipFeedback;
use App\Models\Role;
use App\Models\SmsMessage;
use App\Models\SuggestedEdit;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create();
    }

    // ── Authorization & basic validation ────────────────────────────────────

    public function test_platform_admin_can_merge_users()
    {
        $target = User::factory()->create(['name' => 'Target User']);
        $source = User::factory()->create(['name' => 'Source User']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => '"Source User" has been merged into "Target User".']);

        $this->assertDatabaseMissing('users', ['id' => $source->id]);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_regular_user_cannot_merge_users()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->postJson("/api/admin/users/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $source->id]);
    }

    public function test_unauthenticated_user_cannot_merge()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $response = $this->postJson("/api/admin/users/{$target->id}/merge", [
            'source_id' => $source->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_cannot_merge_user_into_themselves()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->id}/merge", [
                'source_id' => $user->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['error' => 'Cannot merge a user into themselves.']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_cannot_merge_nonexistent_source()
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", [
                'source_id' => 'nonexist',
            ]);

        $response->assertUnprocessable();
    }

    public function test_source_id_is_required_for_merge()
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", []);

        $response->assertUnprocessable();
    }

    public function test_can_merge_when_source_is_deactivated()
    {
        // The whole point of this feature: the "lost access" account is often
        // deactivated, or simply inactive, while the new signup is active.
        $target = User::factory()->create();
        $source = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $source->id]);
    }

    public function test_can_merge_when_target_is_deactivated()
    {
        $target = User::factory()->create(['is_active' => false]);
        $source = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", [
                'source_id' => $source->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $source->id]);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    // ── Trips ────────────────────────────────────────────────────────────────

    public function test_merge_moves_solo_trip_participation()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $trip = Trip::factory()->create();
        $trip->participants()->attach($source->id);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertTrue($trip->fresh()->participants->contains($target->id));
        $this->assertDatabaseMissing('trip_user', ['trip_id' => $trip->id, 'user_id' => $source->id]);
    }

    public function test_merge_deduplicates_shared_trip_participation()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $trip = Trip::factory()->create();
        $trip->participants()->attach([$target->id, $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        // Only one row should remain for this trip/target pair — no unique
        // constraint violation, and the target isn't duplicated.
        $this->assertEquals(
            1,
            \Illuminate\Support\Facades\DB::table('trip_user')
                ->where('trip_id', $trip->id)
                ->where('user_id', $target->id)
                ->count()
        );
        $this->assertDatabaseMissing('trip_user', ['trip_id' => $trip->id, 'user_id' => $source->id]);
    }

    // ── Clubs ────────────────────────────────────────────────────────────────

    public function test_merge_moves_club_membership()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $club = Club::factory()->create();

        $source->clubs()->attach($club->id, ['status' => 'approved', 'is_admin' => true]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $target->refresh();
        $this->assertTrue($target->clubs->contains($club->id));
        $membership = $target->clubs->firstWhere('id', $club->id);
        $this->assertEquals('approved', $membership->pivot->status);
        $this->assertTrue((bool) $membership->pivot->is_admin);
    }

    public function test_merge_combines_shared_club_membership_taking_best_status()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $club = Club::factory()->create();

        // Target is a pending, non-admin member; source is an approved admin.
        $target->clubs()->attach($club->id, ['status' => 'pending', 'is_admin' => false]);
        $source->clubs()->attach($club->id, ['status' => 'approved', 'is_admin' => true]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        // No duplicate row, and the combined membership keeps the best of both.
        $this->assertEquals(
            1,
            \Illuminate\Support\Facades\DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('user_id', $target->id)
                ->count()
        );
        $membership = $target->fresh()->clubs->firstWhere('id', $club->id);
        $this->assertEquals('approved', $membership->pivot->status);
        $this->assertTrue((bool) $membership->pivot->is_admin);
    }

    // ── Medals ───────────────────────────────────────────────────────────────

    public function test_merge_moves_distinct_medals()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $medal = Medal::factory()->create();

        $source->medals()->attach($medal->id, ['awarded_at' => now()]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertTrue($target->fresh()->medals->contains($medal->id));
    }

    public function test_merge_keeps_earliest_award_date_for_shared_medal()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $medal = Medal::factory()->create();

        $earlier = now()->subYear();
        $later = now();

        $target->medals()->attach($medal->id, ['awarded_at' => $later]);
        $source->medals()->attach($medal->id, ['awarded_at' => $earlier]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals(
            1,
            \Illuminate\Support\Facades\DB::table('medal_user')
                ->where('medal_id', $medal->id)
                ->where('user_id', $target->id)
                ->count()
        );
        $pivot = $target->fresh()->medals->firstWhere('id', $medal->id)->pivot;
        $this->assertEquals($earlier->toDateTimeString(), \Illuminate\Support\Carbon::parse($pivot->awarded_at)->toDateTimeString());
    }

    // ── Roles ────────────────────────────────────────────────────────────────

    public function test_merge_moves_roles()
    {
        $target = User::factory()->create();
        $source = User::factory()->dutyOfficer()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole('duty_officer'));
    }

    public function test_merge_deduplicates_shared_role()
    {
        $target = User::factory()->admin()->create();
        $source = User::factory()->admin()->create();
        $roleId = Role::where('slug', 'platform_admin')->value('id');

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals(
            1,
            \Illuminate\Support\Facades\DB::table('role_user')
                ->where('role_id', $roleId)
                ->where('user_id', $target->id)
                ->count()
        );
    }

    // ── Permits ──────────────────────────────────────────────────────────────

    public function test_merge_moves_permit_officer_assignment()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $permit = Permit::factory()->create();

        $permit->officers()->attach($source->id);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertTrue($permit->fresh()->officers->contains($target->id));
    }

    public function test_merge_reassigns_permit_creator()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $permit = Permit::factory()->create(['created_by' => $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        // Critically, this must happen *before* the source is deleted:
        // permits.created_by cascade-deletes, so a naive delete-then-merge
        // would silently destroy the permit.
        $this->assertEquals($target->id, $permit->fresh()->created_by);
        $this->assertDatabaseHas('permits', ['id' => $permit->id]);
    }

    // ── Bookings ─────────────────────────────────────────────────────────────

    public function test_merge_reassigns_bookings()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $applied = Booking::factory()->create(['user_id' => $source->id]);
        $approved = Booking::factory()->approved()->create(['approved_by' => $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $applied->fresh()->user_id);
        $this->assertEquals($target->id, $approved->fresh()->approved_by);
    }

    // ── Collections ──────────────────────────────────────────────────────────

    public function test_merge_moves_collections()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $collection = Collection::factory()->create(['user_id' => $source->id, 'slug' => 'my-trips']);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $collection->fresh()->user_id);
        $this->assertEquals('my-trips', $collection->fresh()->slug);
    }

    public function test_merge_deduplicates_colliding_collection_slugs()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        Collection::factory()->create(['user_id' => $target->id, 'slug' => 'day-trips']);
        $sourceCollection = Collection::factory()->create(['user_id' => $source->id, 'slug' => 'day-trips']);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals('day-trips-2', $sourceCollection->fresh()->slug);
        $this->assertEquals(2, Collection::where('user_id', $target->id)->count());
    }

    // ── Callouts & incidents ─────────────────────────────────────────────────

    public function test_merge_reassigns_callout_creator()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $callout = Callout::factory()->create(['user_id' => $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $callout->fresh()->user_id);
    }

    public function test_merge_moves_linked_callout_participant()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $callout = Callout::factory()->create();

        $participant = CalloutParticipant::create([
            'callout_id' => $callout->id,
            'user_id' => $source->id,
            'name' => $source->name,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $participant->fresh()->user_id);
    }

    public function test_merge_deduplicates_callout_participant_on_same_callout()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $callout = Callout::factory()->create();

        CalloutParticipant::create(['callout_id' => $callout->id, 'user_id' => $target->id, 'name' => $target->name]);
        $sourceParticipant = CalloutParticipant::create(['callout_id' => $callout->id, 'user_id' => $source->id, 'name' => $source->name]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertDatabaseMissing('callout_participants', ['id' => $sourceParticipant->id]);
        $this->assertEquals(
            1,
            CalloutParticipant::where('callout_id', $callout->id)->where('user_id', $target->id)->count()
        );
    }

    public function test_merge_reassigns_incident_controller()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $incident = Incident::factory()->create(['incident_controller_id' => $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $incident->fresh()->incident_controller_id);
    }

    public function test_merge_reassigns_incident_notes()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $note = IncidentNote::factory()->create(['user_id' => $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $note->fresh()->user_id);
    }

    public function test_merge_reassigns_on_call_shifts()
    {
        $target = User::factory()->create();
        $source = User::factory()->dutyOfficer()->create();

        $shift = OnCallShift::factory()->create(['user_id' => $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $shift->fresh()->user_id);
    }

    // ── Pages, Pip feedback, suggested edits, SMS, misc ────────────────────

    public function test_merge_reassigns_pages()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $page = Page::factory()->create(['user_id' => $source->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $page->fresh()->user_id);
    }

    public function test_merge_reassigns_pip_feedback()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $feedback = PipFeedback::create([
            'user_id' => $source->id,
            'rating' => 1,
            'comment' => 'Not helpful',
            'transcript' => [],
            'reviewed' => false,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $feedback->fresh()->user_id);
    }

    public function test_merge_reassigns_suggested_edits()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();
        $cave = Cave::factory()->create();

        $edit = SuggestedEdit::create([
            'user_id' => $source->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => ['description' => 'old'],
            'suggested_data' => ['description' => 'new'],
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $edit->fresh()->user_id);
    }

    public function test_merge_reassigns_sms_message_history()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $sms = SmsMessage::create([
            'provider' => 'twilio',
            'user_id' => $source->id,
            'status' => 'delivered',
            'to_masked' => '•••• 1234',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        $this->assertEquals($target->id, $sms->fresh()->user_id);
    }

    // ── Photo cleanup ────────────────────────────────────────────────────────

    public function test_merge_deletes_source_photo_but_keeps_target_photo()
    {
        Storage::fake('media');

        Storage::disk('media')->put('users/source.jpg', 'source-photo');
        Storage::disk('media')->put('users/target.jpg', 'target-photo');

        $target = User::factory()->create(['photo' => 'users/target.jpg']);
        $source = User::factory()->create(['photo' => 'users/source.jpg']);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        Storage::disk('media')->assertMissing('users/source.jpg');
        Storage::disk('media')->assertExists('users/target.jpg');
        $this->assertEquals('users/target.jpg', $target->fresh()->photo);
    }

    public function test_merge_does_not_delete_default_source_photo()
    {
        Storage::fake('media');

        $target = User::factory()->create();
        $source = User::factory()->create(['photo' => 'default.webp']);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id])
            ->assertOk();

        // Nothing should blow up trying to delete a shared default asset,
        // and no exception should have rolled back the merge.
        $this->assertDatabaseMissing('users', ['id' => $source->id]);
    }

    // ── Preview ──────────────────────────────────────────────────────────────

    public function test_merge_preview_returns_correct_counts()
    {
        $target = User::factory()->create(['name' => 'Target User']);
        $source = User::factory()->create(['name' => 'Source User']);

        $club = Club::factory()->create();
        $target->clubs()->attach($club->id, ['status' => 'approved']);
        $source->clubs()->attach($club->id, ['status' => 'approved']);

        $sharedTrip = Trip::factory()->create();
        $sharedTrip->participants()->attach([$target->id, $source->id]);

        $ownTrip = Trip::factory()->create();
        $ownTrip->participants()->attach($source->id);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/users/{$target->id}/merge-preview?source_id={$source->id}");

        $response->assertOk();
        $response->assertJsonPath('target.name', 'Target User');
        $response->assertJsonPath('source.name', 'Source User');
        $response->assertJsonPath('target.clubs_count', 1);
        $response->assertJsonPath('source.clubs_count', 1);
        $response->assertJsonPath('result.clubs_count', 1); // deduplicated
        $response->assertJsonPath('result.shared_clubs_count', 1);
        $response->assertJsonPath('result.trips_count', 2); // 1 shared + 1 own
        $response->assertJsonPath('result.shared_trips_count', 1);
        $response->assertJsonPath('result.source_will_be_deleted', true);

        // Preview must not mutate anything.
        $this->assertDatabaseHas('users', ['id' => $source->id]);
    }

    public function test_merge_preview_requires_admin()
    {
        $target = User::factory()->create();
        $source = User::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->getJson("/api/admin/users/{$target->id}/merge-preview?source_id={$source->id}");

        $response->assertForbidden();
    }

    public function test_merge_preview_cannot_preview_self()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/users/{$user->id}/merge-preview?source_id={$user->id}");

        $response->assertUnprocessable();
    }

    // ── Full integration ─────────────────────────────────────────────────────

    public function test_merge_with_all_relation_types_together()
    {
        Storage::fake('media');

        $target = User::factory()->create(['name' => 'Keeper']);
        $source = User::factory()->dutyOfficer()->create(['name' => 'Duplicate', 'photo' => 'users/dup.jpg']);
        Storage::disk('media')->put('users/dup.jpg', 'content');

        $trip = Trip::factory()->create();
        $trip->participants()->attach($source->id);

        $club = Club::factory()->create();
        $source->clubs()->attach($club->id, ['status' => 'approved']);

        $medal = Medal::factory()->create();
        $source->medals()->attach($medal->id, ['awarded_at' => now()]);

        $permit = Permit::factory()->create(['created_by' => $source->id]);
        $permit->officers()->attach($source->id);

        $booking = Booking::factory()->create(['user_id' => $source->id]);

        $collection = Collection::factory()->create(['user_id' => $source->id, 'slug' => 'unique-slug']);

        $callout = Callout::factory()->create(['user_id' => $source->id]);

        $incident = Incident::factory()->create(['incident_controller_id' => $source->id]);

        $shift = OnCallShift::factory()->create(['user_id' => $source->id]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$target->id}/merge", ['source_id' => $source->id]);

        $response->assertOk();

        $target->refresh();
        $this->assertTrue($target->trips->contains($trip->id));
        $this->assertTrue($target->clubs->contains($club->id));
        $this->assertTrue($target->medals->contains($medal->id));
        $this->assertTrue($target->hasRole('duty_officer'));
        $this->assertEquals($target->id, $permit->fresh()->created_by);
        $this->assertTrue($permit->fresh()->officers->contains($target->id));
        $this->assertEquals($target->id, $booking->fresh()->user_id);
        $this->assertEquals($target->id, $collection->fresh()->user_id);
        $this->assertEquals($target->id, $callout->fresh()->user_id);
        $this->assertEquals($target->id, $incident->fresh()->incident_controller_id);
        $this->assertEquals($target->id, $shift->fresh()->user_id);

        Storage::disk('media')->assertMissing('users/dup.jpg');
        $this->assertDatabaseMissing('users', ['id' => $source->id]);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
