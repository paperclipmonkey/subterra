<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The complaints mechanism required by the Online Safety Act: members must be
 * able to report content and conduct, and those reports must reach a moderator.
 */
class ReportingTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_member_can_report_a_trip()
    {
        $reporter = User::factory()->create();
        $trip = Trip::factory()->create();

        $this->actingAs($reporter, 'sanctum');
        $response = $this->postJson(route('reports.store'), [
            'reportable_type' => 'trip',
            'reportable_id' => $trip->short_id,
            'category' => 'harassment',
            'details' => 'The description names someone who was not there.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => Trip::class,
            'reportable_id' => (string) $trip->id,
            'category' => 'harassment',
            'status' => 'open',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_member_can_report_another_member()
    {
        $reporter = User::factory()->create();
        $subject = User::factory()->create();

        $this->actingAs($reporter, 'sanctum');
        $this->postJson(route('reports.store'), [
            'reportable_type' => 'user',
            'reportable_id' => $subject->id,
            'category' => 'child_safety',
        ])->assertCreated();

        $this->assertDatabaseHas('reports', [
            'reportable_type' => User::class,
            'reportable_id' => $subject->id,
            'category' => 'child_safety',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_deactivated_member_can_still_be_reported()
    {
        // Users carry a global active scope. A report about a deactivated account
        // is exactly the kind that matters, so the existence check must bypass it.
        $reporter = User::factory()->create();
        $subject = User::factory()->create();
        $subject->forceFill(['is_active' => false])->save();

        $this->actingAs($reporter, 'sanctum');
        $this->postJson(route('reports.store'), [
            'reportable_type' => 'user',
            'reportable_id' => $subject->id,
            'category' => 'harassment',
        ])->assertCreated();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reporting_the_same_thing_twice_does_not_create_a_duplicate()
    {
        $reporter = User::factory()->create();
        $trip = Trip::factory()->create();
        $payload = [
            'reportable_type' => 'trip',
            'reportable_id' => $trip->short_id,
            'category' => 'spam',
        ];

        $this->actingAs($reporter, 'sanctum');
        $this->postJson(route('reports.store'), $payload)->assertCreated();
        // Second submission is a success, not an error — see the controller.
        $this->postJson(route('reports.store'), $payload)->assertOk();

        $this->assertSame(1, Report::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function two_different_members_can_report_the_same_thing()
    {
        $trip = Trip::factory()->create();
        $payload = [
            'reportable_type' => 'trip',
            'reportable_id' => $trip->short_id,
            'category' => 'spam',
        ];

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson(route('reports.store'), $payload)->assertCreated();

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson(route('reports.store'), $payload)->assertCreated();

        $this->assertSame(2, Report::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_a_report_against_an_arbitrary_model_class()
    {
        // The type is an allowlisted short name, not a class string — otherwise a
        // caller could aim a report at any model in the application.
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson(route('reports.store'), [
            'reportable_type' => 'App\Models\Callout',
            'reportable_id' => '1',
            'category' => 'spam',
        ])->assertStatus(422)->assertJsonValidationErrors('reportable_type');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_a_report_against_something_that_does_not_exist()
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        // A non-numeric identifier is the realistic case: trips are addressed by
        // short_id, so an unknown one must 404 rather than blow up the query.
        $this->postJson(route('reports.store'), [
            'reportable_type' => 'trip',
            'reportable_id' => 'nOtAsHoRt',
            'category' => 'spam',
        ])->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_a_category_a_member_cannot_choose()
    {
        // data_objection is raised by the platform, never picked by a reporter.
        $this->actingAs(User::factory()->create(), 'sanctum');
        $trip = Trip::factory()->create();

        $this->postJson(route('reports.store'), [
            'reportable_type' => 'trip',
            'reportable_id' => $trip->short_id,
            'category' => 'data_objection',
        ])->assertStatus(422)->assertJsonValidationErrors('category');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reporting_requires_authentication()
    {
        $trip = Trip::factory()->create();

        $this->postJson(route('reports.store'), [
            'reportable_type' => 'trip',
            'reportable_id' => $trip->short_id,
            'category' => 'spam',
        ])->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function only_platform_admins_can_see_the_moderation_queue()
    {
        Report::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson(route('admin.reports.index'))->assertStatus(403);

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->getJson(route('admin.reports.index'))->assertOk()->assertJsonCount(1, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_queue_puts_urgent_reports_first()
    {
        Report::factory()->create(['category' => 'spam']);
        $urgent = Report::factory()->urgent()->create();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->getJson(route('admin.reports.index'));

        $response->assertOk();
        $this->assertSame($urgent->id, $response->json('data.0.id'));
        $this->assertTrue($response->json('data.0.is_urgent'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_queue_shows_open_reports_by_default()
    {
        Report::factory()->create();
        Report::factory()->resolved()->create();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');

        $this->getJson(route('admin.reports.index'))->assertJsonCount(1, 'data');
        $this->getJson(route('admin.reports.index', ['status' => 'all']))->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_admin_can_resolve_a_report_and_the_record_is_kept()
    {
        $report = Report::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum');
        $this->putJson(route('admin.reports.update', $report), [
            'status' => 'actioned',
            'resolution_note' => 'Photo removed and the uploader spoken to.',
        ])->assertOk();

        $report->refresh();
        $this->assertSame('actioned', $report->status);
        $this->assertSame($admin->id, $report->handled_by);
        $this->assertNotNull($report->handled_at);
        // Resolving must never delete the record of what was reported.
        $this->assertDatabaseHas('reports', ['id' => $report->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_resolved_report_can_be_reopened()
    {
        $report = Report::factory()->resolved('dismissed')->create();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->putJson(route('admin.reports.update', $report), ['status' => 'open'])->assertOk();

        $report->refresh();
        $this->assertSame('open', $report->status);
        $this->assertNull($report->handled_by);
        $this->assertNull($report->handled_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_queue_reports_its_counts()
    {
        Report::factory()->count(2)->create();
        Report::factory()->urgent()->create();
        Report::factory()->resolved()->create();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->getJson(route('admin.reports.counts'));

        $response->assertOk();
        $this->assertSame(3, $response->json('open'));
        $this->assertSame(1, $response->json('urgent_open'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_trip_reported_by_short_id_resolves_in_the_queue()
    {
        // Regression guard. The SPA addresses a trip by its short_id, because that
        // is what TripResource publishes as `id`. Resolving that against the bigint
        // primary key is a hard error on PostgreSQL and a silent miss on SQLite.
        $trip = Trip::factory()->create(['name' => 'Gaping Gill exchange']);

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson(route('reports.store'), [
            'reportable_type' => 'trip',
            'reportable_id' => $trip->short_id,
            'category' => 'privacy',
        ])->assertCreated();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->getJson(route('admin.reports.index'));

        $response->assertOk();
        $this->assertSame('Gaping Gill exchange', $response->json('data.0.target.label'));
        $this->assertSame('/trips/'.$trip->short_id, $response->json('data.0.target.url'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_report_stays_readable_after_its_target_is_deleted()
    {
        $trip = Trip::factory()->create();
        $report = Report::factory()->create([
            'reportable_type' => Trip::class,
            'reportable_id' => (string) $trip->id,
        ]);
        $trip->delete();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->getJson(route('admin.reports.index'));

        $response->assertOk();
        $this->assertSame($report->id, $response->json('data.0.id'));
        $this->assertStringContainsString('deleted', $response->json('data.0.target.label'));
    }
}
