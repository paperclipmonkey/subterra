<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_exports_user_data_in_json_format()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Caving enthusiast',
        ]);

        // Create some related data
        $trip = Trip::factory()->create(['description' => 'Great cave trip']);
        $trip->participants()->attach($user->id);

        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'description' => 'Overdue at Swildons',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/user/export');

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="subterra_data_export_'.now()->format('Y-m-d').'.json"');

        $response->assertJsonStructure([
            'profile' => ['name', 'email', 'bio', 'created_at'],
            'clubs',
            'medals',
            'trips' => [
                '*' => ['id', 'start_time', 'end_time', 'description'],
            ],
            'callouts' => [
                '*' => ['id', 'callout_time', 'description', 'status'],
            ],
        ]);

        $response->assertJsonFragment(['name' => 'John Doe']);
        $response->assertJsonFragment(['description' => 'Great cave trip']);
        $response->assertJsonFragment(['description' => 'Overdue at Swildons']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_export_data()
    {
        $response = $this->getJson('/api/user/export');
        $response->assertUnauthorized();
    }
}
