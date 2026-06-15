<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaveSystem;
use App\Models\CaveSystemMapOverlay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaveSystemMapOverlayTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_list_overlays_for_a_cave_system()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();
        CaveSystemMapOverlay::factory()->count(2)->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $response = $this->getJson("/api/cave_systems/{$caveSystem->id}/map_overlays");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admins_can_upload_a_geotiff_overlay()
    {
        Storage::fake('media');
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();

        $file = UploadedFile::fake()->create('survey.tif', 200, 'image/tiff');

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/map_overlays", [
            'name' => 'Survey sheet 1',
            'file' => $file,
            'bounds' => [-2.65, 51.82, -2.60, 51.85],
            'opacity' => 0.6,
            'visible_by_default' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Survey sheet 1')
            ->assertJsonPath('data.opacity', 0.6)
            ->assertJsonPath('data.bounds', [-2.65, 51.82, -2.60, 51.85]);

        $this->assertDatabaseHas('cave_system_map_overlays', [
            'cave_system_id' => $caveSystem->id,
            'name' => 'Survey sheet 1',
        ]);

        $overlay = CaveSystemMapOverlay::first();
        Storage::disk('media')->assertExists("cave_system_overlays/{$caveSystem->id}/{$overlay->filename}");
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_non_geotiff_files()
    {
        Storage::fake('media');
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();

        $file = UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf');

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/map_overlays", [
            'name' => 'Bad file',
            'file' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admins_cannot_upload_overlays()
    {
        Storage::fake('media');
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();

        $file = UploadedFile::fake()->create('survey.tif', 100, 'image/tiff');

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/map_overlays", [
            'name' => 'Survey',
            'file' => $file,
        ]);

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admins_can_update_overlay_metadata()
    {
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();
        $overlay = CaveSystemMapOverlay::factory()->create([
            'cave_system_id' => $caveSystem->id,
            'opacity' => 0.8,
        ]);

        $response = $this->putJson("/api/cave_systems/{$caveSystem->id}/map_overlays/{$overlay->id}", [
            'name' => 'Renamed overlay',
            'opacity' => 0.3,
            'visible_by_default' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Renamed overlay')
            ->assertJsonPath('data.opacity', 0.3)
            ->assertJsonPath('data.visible_by_default', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admins_can_delete_an_overlay()
    {
        Storage::fake('media');
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();
        $overlay = CaveSystemMapOverlay::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);
        Storage::disk('media')->put("cave_system_overlays/{$caveSystem->id}/{$overlay->filename}", 'data');

        $response = $this->deleteJson("/api/cave_systems/{$caveSystem->id}/map_overlays/{$overlay->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('cave_system_map_overlays', ['id' => $overlay->id]);
        Storage::disk('media')->assertMissing("cave_system_overlays/{$caveSystem->id}/{$overlay->filename}");
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admins_cannot_delete_overlays()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();
        $overlay = CaveSystemMapOverlay::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $response = $this->deleteJson("/api/cave_systems/{$caveSystem->id}/map_overlays/{$overlay->id}");

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function overlays_are_included_in_cave_system_show()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();
        CaveSystemMapOverlay::factory()->create([
            'cave_system_id' => $caveSystem->id,
            'name' => 'Included overlay',
        ]);

        $response = $this->getJson("/api/cave_systems/{$caveSystem->id}");

        $response->assertOk()
            ->assertJsonPath('data.map_overlays.0.name', 'Included overlay');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function overlays_are_deleted_when_cave_system_is_deleted()
    {
        $caveSystem = CaveSystem::factory()->create();
        $overlay = CaveSystemMapOverlay::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $caveSystem->delete();

        $this->assertDatabaseMissing('cave_system_map_overlays', ['id' => $overlay->id]);
    }
}
