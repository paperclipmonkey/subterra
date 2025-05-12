<?php

namespace Tests\Feature;

use App\Models\CaveSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaveSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Optionally, create an admin user and authenticate if needed
        $this->user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_show_a_cave_system()
    {
        $caveSystem = CaveSystem::factory()->create();

        $response = $this->getJson('/api/cave_systems/' . $caveSystem->id);

        $response->assertOk()
            ->assertJsonFragment(['id' => $caveSystem->id])
            ->assertJsonStructure(['data' => ['files']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_a_cave_system()
    {
        Storage::fake('media');
        $caveSystem = CaveSystem::factory()->create();

        $newFile = UploadedFile::fake()->create('newfile.pdf', 100, 'application/pdf');
        $details = ['Some details'];

        $data = [
            'name' => 'Updated Name',
            'new_files' => [$newFile],
            'new_file_details' => $details,
        ];

        $response = $this->json('POST', "/api/cave_systems/{$caveSystem->id}?_method=PUT", $data);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        // New file exists
        $caveSystem->refresh();
        $this->assertCount(1, $caveSystem->files);
        Storage::disk('media')->assertExists("cave_system_files/{$caveSystem->id}/" . $caveSystem->files->first()->filename);
    }
}