<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DataMinimizationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_scrubs_sensitive_data_from_old_resolved_callouts()
    {
        $user = User::factory()->create();

        // 1. Old (31 days) Resolved Callout - SHOULD BE SCRUBBED
        $oldResolved = Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'resolved',
            'created_at' => Carbon::now()->subDays(31),
            'car_details' => 'Silver Focus',
            'team_details' => 'Me and a friend',
            'trip_plan' => 'Detailed plan',
        ]);

        // 2. Old (31 days) Active Callout - SHOULD NOT BE SCRUBBED (Safety first)
        $oldActive = Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'created_at' => Carbon::now()->subDays(31),
            'car_details' => 'Silver Focus',
        ]);

        // 3. Fresh (5 days) Resolved Callout - SHOULD NOT BE SCRUBBED
        $freshResolved = Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'resolved',
            'created_at' => Carbon::now()->subDays(5),
            'car_details' => 'Silver Focus',
        ]);

        // Run the command
        Artisan::call('callouts:purge-sensitive-data');

        // Assert Old Resolved is scrubbed
        $oldResolved->refresh();
        $this->assertNull($oldResolved->car_details);
        $this->assertNull($oldResolved->team_details);
        $this->assertNull($oldResolved->trip_plan);

        // Assert Old Active is untouched
        $oldActive->refresh();
        $this->assertEquals('Silver Focus', $oldActive->car_details);

        // Assert Fresh Resolved is untouched
        $freshResolved->refresh();
        $this->assertEquals('Silver Focus', $freshResolved->car_details);
    }
}
