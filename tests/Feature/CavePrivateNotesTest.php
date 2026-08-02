<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CavePrivateNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TagSeeder::class);
    }

    #[Test]
    public function private_notes_never_appear_in_the_public_cave_resource(): void
    {
        $cave = Cave::factory()->create(['private_notes' => 'Landowner: call Bob on 555.']);

        $response = $this->actingAs(User::factory()->withApprovedClub()->create())
            ->getJson('/api/caves/'.$cave->slug);

        $response->assertStatus(200);
        $this->assertStringNotContainsString('Landowner', $response->getContent());
        $response->assertJsonMissingPath('data.private_notes');
    }

    #[Test]
    public function the_cave_resource_exposes_can_manage_and_private_fields_only_to_managers(): void
    {
        $cave = Cave::factory()->create(['private_notes' => 'Manager only note', 'visibility' => 'admin_only']);
        $admin = User::factory()->dataAdmin()->create();

        // Data admin: can_manage true, private fields present.
        $this->actingAs($admin)
            ->getJson('/api/caves/'.$cave->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.private_notes', 'Manager only note')
            ->assertJsonPath('data.visibility', 'admin_only');

        // Ordinary user: can_manage false, no private fields. (Use a public cave
        // so the record itself is viewable.)
        $publicCave = Cave::factory()->create(['private_notes' => 'secret', 'visibility' => 'public']);
        $response = $this->actingAs(User::factory()->withApprovedClub()->create())
            ->getJson('/api/caves/'.$publicCave->slug);
        $response->assertStatus(200)->assertJsonPath('data.can_manage', false);
        $response->assertJsonMissingPath('data.private_notes');
        $response->assertJsonMissingPath('data.visibility');
    }

    #[Test]
    public function a_data_admin_can_set_private_notes(): void
    {
        $cave = Cave::factory()->create();
        $admin = User::factory()->dataAdmin()->create();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->putJson('/api/caves/'.$cave->slug, ['private_notes' => 'Access via farm gate.'])
            ->assertStatus(200);

        $this->assertEquals('Access via farm gate.', $cave->fresh()->private_notes);
    }
}
