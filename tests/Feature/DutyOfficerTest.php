<?php

namespace Tests\Feature;

use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyOfficerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_correct_duty_officer_when_one_is_scheduled()
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'name' => 'Scheduled Officer',
        ]);

        OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        // Create another admin who is NOT on call
        User::factory()->create([
            'is_admin' => true,
            'name' => 'Other Admin',
        ]);

        $this->actingAs($user)
            ->get('/api/duty-officers/current')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'name',
                    'photo',
                    'next_gap_start',
                    'is_covered'
                ]
            ]);
    }

    public function test_it_correctly_calculates_next_gap()
    {
        $user = User::factory()->create(['is_admin' => true]);
        
        // Shift 1: Now -> +1 hour
        OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        // Shift 2: +1 hour -> +2 hours (Continuous)
        OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
        ]);

        // Shift 3: +3 hours -> +4 hours (Gap of 1 hour)
        OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->addHours(3),
            'end_at' => now()->addHours(4),
        ]);

        $response = $this->actingAs($user)->get('/api/duty-officers/current');
        
        $response->assertStatus(200);
        
        // Gap should start at end of Shift 2 (+2 hours from now)
        $nextGap = $response->json('data.next_gap_start');
        $this->assertEquals(
            now()->addHours(2)->startOfSecond()->toDateTimeString(), 
            \Carbon\Carbon::parse($nextGap)->startOfSecond()->toDateTimeString()
        );
    }

    public function test_it_returns_200_with_is_covered_false_when_no_duty_officer_is_scheduled()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/api/duty-officers/current')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => null,
                    'photo' => null,
                    'is_covered' => false
                ]
            ]);
    }

    public function test_deleting_shift_returns_affected_callouts_info()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Create a shift
        $shift = OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(3),
        ]);

        // Create callouts during this shift
        $user = User::factory()->create();
        $callout1 = \App\Models\Callout::factory()->create([
            'user_id' => $user->id,
            'callout_time' => now()->addHours(2),
            'status' => 'active',
        ]);
        
        $callout2 = \App\Models\Callout::factory()->create([
            'user_id' => $user->id,
            'callout_time' => now()->addHours(2)->addMinutes(30),
            'status' => 'triggered',
        ]);

        // Create a callout outside the shift period (should not be affected)
        \App\Models\Callout::factory()->create([
            'user_id' => $user->id,
            'callout_time' => now()->addHours(5),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/admin/shifts/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'affected_callouts',
                'count'
            ]);

        $this->assertEquals(2, $response->json('count'));
        $this->assertCount(2, $response->json('affected_callouts'));
    }

    public function test_deleting_shift_with_no_callouts_returns_empty_array()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $shift = OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(3),
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/admin/shifts/{$shift->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Shift removed',
                'count' => 0
            ]);

        $this->assertEmpty($response->json('affected_callouts'));
    }
}
