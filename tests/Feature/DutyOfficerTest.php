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
            ->assertJson([
                'data' => [
                    'name' => 'Scheduled Officer',
                ],
            ]);
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
