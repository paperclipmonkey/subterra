<?php

namespace Tests\Feature\Admin;

use App\Models\Callout;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnCallShiftConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'is_approved' => true]);
    }

    public function test_cannot_delete_shift_covering_active_callout()
    {
        $shift = OnCallShift::factory()->create([
            'start_at' => now()->copy()->startOfHour(),
            'end_at' => now()->copy()->addHours(4),
        ]);

        Callout::factory()->create([
            'status' => 'active',
            'callout_time' => now()->copy()->addHours(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/shifts/{$shift->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cannot remove shift: would leave 1 callout(s) unmonitored.']);
        $this->assertDatabaseHas('on_call_shifts', ['id' => $shift->id]);
    }

    public function test_can_delete_shift_if_another_covers_the_callout()
    {
        $shift1 = OnCallShift::factory()->create([
            'start_at' => now()->copy()->startOfHour(),
            'end_at' => now()->copy()->addHours(4),
        ]);

        $shift2 = OnCallShift::factory()->create([
            'start_at' => now()->copy()->startOfHour(),
            'end_at' => now()->copy()->addHours(4),
        ]);

        Callout::factory()->create([
            'status' => 'active',
            'callout_time' => now()->copy()->addHours(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/shifts/{$shift1->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('on_call_shifts', ['id' => $shift1->id]);
    }

    public function test_cannot_shorten_shift_leaving_callout_unmonitored()
    {
        $shift = OnCallShift::factory()->create([
            'start_at' => now()->copy()->startOfHour(),
            'end_at' => now()->copy()->addHours(4),
        ]);

        Callout::factory()->create([
            'status' => 'active',
            'callout_time' => now()->copy()->addHours(3),
        ]);

        // Try to shorten end_at to before the callout time
        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/shifts/{$shift->id}", [
                'user_id' => $shift->user_id,
                'start_at' => $shift->start_at->toIso8601String(),
                'end_at' => now()->copy()->addHours(2)->toIso8601String(),
            ]);

        $response->assertStatus(422);
        $this->assertEquals($shift->fresh()->end_at->timestamp, $shift->end_at->timestamp);
    }

    public function test_can_shorten_shift_if_callout_is_still_covered()
    {
        $shift = OnCallShift::factory()->create([
            'start_at' => now()->copy()->startOfHour(),
            'end_at' => now()->copy()->addHours(4),
        ]);

        Callout::factory()->create([
            'status' => 'active',
            'callout_time' => now()->copy()->addHours(1),
        ]);

        // Shorten end_at but keep callout covered
        $newEnd = now()->copy()->addHours(2);
        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/shifts/{$shift->id}", [
                'user_id' => $shift->user_id,
                'start_at' => $shift->start_at->toIso8601String(),
                'end_at' => $newEnd->toIso8601String(),
            ]);

        $response->assertStatus(200);
        $this->assertEquals($shift->fresh()->end_at->timestamp, $newEnd->timestamp);
    }
}
