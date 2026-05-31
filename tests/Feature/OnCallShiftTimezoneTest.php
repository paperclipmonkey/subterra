<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OnCallShiftTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->dutyOfficer()->create();
        $this->actingAs($this->user);
    }

    public function test_shift_created_with_iso8601_offset_stores_correct_utc(): void
    {
        // Simulate BST (UTC+1) frontend sending: 2025-06-15T07:30:00+01:00
        // This should be stored as 2025-06-15T06:30:00 UTC
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-06-15T07:30:00+01:00',
            'end_at' => '2025-06-15T23:30:00+01:00',
        ]);

        $response->assertStatus(200);

        $shift = OnCallShift::latest()->first();
        $this->assertEquals('2025-06-15 06:30:00', $shift->start_at->toDateTimeString());
        $this->assertEquals('2025-06-15 22:30:00', $shift->end_at->toDateTimeString());
    }

    public function test_shift_created_with_utc_z_suffix_stores_correct_utc(): void
    {
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-06-15T06:30:00Z',
            'end_at' => '2025-06-15T22:30:00Z',
        ]);

        $response->assertStatus(200);

        $shift = OnCallShift::latest()->first();
        $this->assertEquals('2025-06-15 06:30:00', $shift->start_at->toDateTimeString());
        $this->assertEquals('2025-06-15 22:30:00', $shift->end_at->toDateTimeString());
    }

    public function test_shift_overlap_detection_works_across_timezone_offsets(): void
    {
        // Create shift: 06:30-22:30 UTC (07:30-23:30 BST)
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-06-15 06:30:00'),
            'end_at' => Carbon::parse('2025-06-15 22:30:00'),
        ]);

        // Try to create overlapping shift sent as BST offset
        // 22:00 BST = 21:00 UTC, which is inside 06:30-22:30 UTC
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-06-15T22:00:00+01:00',
            'end_at' => '2025-06-16T07:30:00+01:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_shift_update_with_timezone_offset(): void
    {
        $shift = OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-06-15 06:30:00'),
            'end_at' => Carbon::parse('2025-06-15 22:30:00'),
        ]);

        // Update with BST offset - extending end time by 1 hour
        $response = $this->putJson("/api/admin/shifts/{$shift->id}", [
            'user_id' => $this->user->id,
            'start_at' => '2025-06-15T07:30:00+01:00',
            'end_at' => '2025-06-16T00:30:00+01:00',
        ]);

        $response->assertStatus(200);

        $shift->refresh();
        $this->assertEquals('2025-06-15 06:30:00', $shift->start_at->toDateTimeString());
        $this->assertEquals('2025-06-15 23:30:00', $shift->end_at->toDateTimeString());
    }

    public function test_non_overlapping_shift_with_different_timezone_offset(): void
    {
        // Create shift: 06:30-22:30 UTC
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-06-15 06:30:00'),
            'end_at' => Carbon::parse('2025-06-15 22:30:00'),
        ]);

        // Create abutting shift sent as BST offset
        // 23:30 BST = 22:30 UTC (exactly at the end of the previous shift)
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-06-15T23:30:00+01:00',
            'end_at' => '2025-06-16T07:30:00+01:00',
        ]);

        $response->assertStatus(200);
    }
}
