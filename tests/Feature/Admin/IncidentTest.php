<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Incident;
use App\Models\Callout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_incidents_dashboard_without_sql_error()
    {
        // Arrange
        $admin = User::factory()->create(['is_admin' => true, 'is_approved' => true]);
        
        // Create incidents with various statuses to exercise the ordering logic
        // We need callouts for incidents really, assuming factories exist
        // Or manually create:
        
        $u = User::factory()->create();
        
        $c1 = Callout::factory()->create(['user_id' => $u->id]);
        Incident::create(['callout_id' => $c1->id, 'status' => 'open']);
        
        $c2 = Callout::factory()->create(['user_id' => $u->id]);
        Incident::create(['callout_id' => $c2->id, 'status' => 'managed']);

        $c3 = Callout::factory()->create(['user_id' => $u->id]);
        Incident::create(['callout_id' => $c3->id, 'status' => 'resolved']);

        // Act
        $response = $this->actingAs($admin)
            ->getJson('/api/admin/incidents');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }
}
