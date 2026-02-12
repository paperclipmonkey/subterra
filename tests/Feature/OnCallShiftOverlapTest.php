<?php

namespace Tests\Feature;

use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OnCallShiftOverlapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup admin user
        $this->user = User::factory()->dutyOfficer()->create();
        $this->actingAs($this->user);
    }

    public function test_cannot_create_shift_start_overlap()
    {
        // User A has shift 10:00 - 12:00
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-01-01 10:00:00'),
            'end_at' => Carbon::parse('2025-01-01 12:00:00'),
        ]);

        // Same user tries shift 11:00 - 13:00 (Overlap start)
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-01-01 11:00:00',
            'end_at' => '2025-01-01 13:00:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_create_shift_end_overlap()
    {
        // User A has shift 10:00 - 12:00
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-01-01 10:00:00'),
            'end_at' => Carbon::parse('2025-01-01 12:00:00'),
        ]);

        // Same user tries shift 09:00 - 11:00 (Overlap end)
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-01-01 09:00:00',
            'end_at' => '2025-01-01 11:00:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_create_shift_inside_overlap()
    {
        // User A has shift 10:00 - 12:00
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-01-01 10:00:00'),
            'end_at' => Carbon::parse('2025-01-01 12:00:00'),
        ]);

        // Same user tries shift 10:30 - 11:30 (Inside)
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-01-01 10:30:00',
            'end_at' => '2025-01-01 11:30:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_create_shift_surrounding_overlap()
    {
        // User A has shift 10:00 - 12:00
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-01-01 10:00:00'),
            'end_at' => Carbon::parse('2025-01-01 12:00:00'),
        ]);

        // Same user tries shift 09:00 - 13:00 (Surrounding)
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $this->user->id,
            'start_at' => '2025-01-01 09:00:00',
            'end_at' => '2025-01-01 13:00:00',
        ]);

        $response->assertStatus(409);
    }

    public function test_cannot_create_overlap_with_different_user()
    {
        // User A has shift 10:00 - 12:00
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-01-01 10:00:00'),
            'end_at' => Carbon::parse('2025-01-01 12:00:00'),
        ]);

        // User B tries shift 11:00 - 13:00 (Overlap start)
        $userB = User::factory()->dutyOfficer()->create();
        
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $userB->id,
            'start_at' => '2025-01-01 11:00:00',
            'end_at' => '2025-01-01 13:00:00',
        ]);
        
        // This should fail now as we want NO overlapping shifts at all
        $response->assertStatus(409);
    }

    public function test_can_create_abutting_shift()
    {
        // User A has shift 10:00 - 12:00
        OnCallShift::factory()->create([
            'user_id' => $this->user->id,
            'start_at' => Carbon::parse('2025-01-01 10:00:00'),
            'end_at' => Carbon::parse('2025-01-01 12:00:00'),
        ]);

        // User B tries shift 12:00 - 14:00 (Abutting)
        $userB = User::factory()->dutyOfficer()->create();
        
        $response = $this->postJson('/api/admin/shifts', [
            'user_id' => $userB->id,
            'start_at' => '2025-01-01 12:00:00',
            'end_at' => '2025-01-01 14:00:00',
        ]);

        $response->assertStatus(200);
    }
}
