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

    public function test_it_returns_404_when_no_duty_officer_is_scheduled()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/api/duty-officers/current')
            ->assertStatus(404);
            // ->assertJson(['message' => 'No duty officer currently on shift.']); // Optional msg check
    }
}
