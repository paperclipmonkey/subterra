<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Callout;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_acknowledge_assigns_controller_and_records_note()
    {
        $do = User::factory()->dutyOfficer()->create(['name' => 'Jane']);
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $service = app(IncidentService::class);

        $this->assertTrue($service->acknowledge($incident, $do, 'the dashboard'));

        $incident->refresh();
        $this->assertEquals($do->id, $incident->incident_controller_id);
        $this->assertEquals('managed', $incident->status);
        $this->assertNotNull($incident->acknowledged_at);
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'user_id' => $do->id,
            'content' => 'Jane acknowledged the incident via the dashboard and is assuming the Controller role.',
        ]);
    }

    public function test_concurrent_acknowledgements_with_stale_models_only_win_once()
    {
        // Regression (M2): two duty officers acknowledging at the same moment both pass
        // the in-memory check on their own stale copy. The transactional row-lock
        // re-check must let only the first become the Controller.
        $do1 = User::factory()->dutyOfficer()->create(['name' => 'First DO']);
        $do2 = User::factory()->dutyOfficer()->create(['name' => 'Second DO']);
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $service = app(IncidentService::class);

        // Two independent (and soon mutually stale) in-memory copies.
        $copyA = Incident::find($incident->id);
        $copyB = Incident::find($incident->id);

        $this->assertTrue($service->acknowledge($copyA, $do1, 'an SMS reply'));

        // copyB still believes the incident is unacknowledged; the DB gate must stop it.
        $this->assertFalse($service->acknowledge($copyB, $do2, 'a voice call'), 'The second acknowledgement must lose the atomic gate.');

        $incident->refresh();
        $this->assertEquals($do1->id, $incident->incident_controller_id, 'The first acknowledger must remain the Controller.');
        $this->assertSame(1, $incident->notes()->count(), 'Only the winning acknowledgement may record a note.');
    }
}
