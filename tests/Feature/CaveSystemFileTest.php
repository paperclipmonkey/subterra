<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\CaveSystemFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaveSystemFileTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: CaveSystem, 1: Cave, 2: User} A system whose data is managed by a data admin. */
    private function managedSystem(): array
    {
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);
        $manager = User::factory()->dataAdmin()->create();

        return [$system, $cave, $manager];
    }

    #[Test]
    public function a_registry_manager_can_upload_a_system_file_with_kind_visibility_and_credits(): void
    {
        Storage::fake('media');
        Bus::fake();
        [$system, , $editor] = $this->managedSystem();

        $response = $this->actingAs($editor)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/cave_systems/{$system->id}/files", [
                'file' => UploadedFile::fake()->image('historic.jpg'),
                'kind' => 'historic',
                'visibility' => 'private',
                'title' => '1923 Survey',
                'photographer' => 'A. Caver',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.kind', 'historic')
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.photographer', 'A. Caver');

        $this->assertDatabaseHas('cave_system_files', [
            'cave_system_id' => $system->id,
            'kind' => 'historic',
            'visibility' => 'private',
        ]);
    }

    #[Test]
    public function a_non_manager_cannot_upload_a_system_file(): void
    {
        Storage::fake('media');
        $system = CaveSystem::factory()->create();

        $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/cave_systems/{$system->id}/files", [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function the_files_index_hides_private_files_from_non_managers(): void
    {
        [$system, , $editor] = $this->managedSystem();
        CaveSystemFile::factory()->for($system, 'caveSystem')->create(['title' => 'Public Survey']);
        CaveSystemFile::factory()->for($system, 'caveSystem')->private()->create(['title' => 'Secret Map']);

        // Manager sees both.
        $this->actingAs($editor)
            ->getJson("/api/cave_systems/{$system->id}/files")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Ordinary user sees only the public one.
        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/cave_systems/{$system->id}/files");
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Public Survey', $response->json('data.0.title'));
    }

    #[Test]
    public function private_system_files_only_reach_managers_on_the_cave_resource(): void
    {
        [$system, $cave, $editor] = $this->managedSystem();
        CaveSystemFile::factory()->for($system, 'caveSystem')->create(['title' => 'Public Survey']);
        CaveSystemFile::factory()->for($system, 'caveSystem')->private()->create(['title' => 'Private Map']);

        // Manager: sees both via the cave page.
        $managerTitles = collect(
            $this->actingAs($editor)->getJson('/api/caves/'.$cave->slug)->json('data.system.files')
        )->pluck('title');
        $this->assertTrue($managerTitles->contains('Public Survey'));
        $this->assertTrue($managerTitles->contains('Private Map'));

        // Approved-club non-manager: sees public only, never the private file.
        $body = $this->actingAs(User::factory()->withApprovedClub()->create())
            ->getJson('/api/caves/'.$cave->slug)->getContent();
        $this->assertStringContainsString('Public Survey', $body);
        $this->assertStringNotContainsString('Private Map', $body);
    }

    #[Test]
    public function a_manager_can_delete_a_system_file(): void
    {
        Storage::fake('media');
        [$system, , $editor] = $this->managedSystem();
        $file = CaveSystemFile::factory()->for($system, 'caveSystem')->create();

        $this->actingAs($editor)
            ->withHeaders(['Accept' => 'application/json'])
            ->deleteJson("/api/cave_systems/{$system->id}/files/{$file->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('cave_system_files', ['id' => $file->id]);
    }
}
