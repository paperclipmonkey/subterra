<?php

declare(strict_types=1);

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
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_show_a_cave_system()
    {
        $caveSystem = CaveSystem::factory()->create();

        $response = $this->getJson('/api/cave_systems/'.$caveSystem->id);

        $this->assertEquals(200, $response->status());
        $response->assertJsonFragment(['id' => $caveSystem->id])
            ->assertJsonStructure(['data' => ['files']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_a_cave_system_and_dispatch_job()
    {
        Storage::fake('media');
        \Illuminate\Support\Facades\Queue::fake();

        $caveSystem = CaveSystem::factory()->create();
        $file = UploadedFile::fake()->image('test.jpg');

        $data = [
            'new_files' => [$file],
            'new_file_details' => ['Test Description'],
        ];

        $response = $this->json('POST', "/api/cave_systems/{$caveSystem->id}?_method=PUT", $data);

        $this->assertEquals(200, $response->status());

        // New file exists
        $caveSystem->refresh();
        $this->assertCount(1, $caveSystem->files);
        $fileRecord = $caveSystem->files->first();

        // Assert Job was dispatched
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\GenerateCaveSystemThumbnail::class, function ($job) use ($fileRecord) {
            return $job->file->id === $fileRecord->id;
        });

        Storage::disk('media')->assertExists("cave_system_files/{$caveSystem->id}/".$caveSystem->files->first()->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_treats_blank_length_as_zero_instead_of_violating_not_null()
    {
        // Reproduces the Gaping Gill case: a CNCC-imported system whose length is 0
        // is edited, and the form submits an empty length/vertical_range. That must
        // save as 0 (keeping the rest of the record), not 500 on the NOT NULL column.
        $caveSystem = CaveSystem::factory()->create(['length' => 0, 'vertical_range' => 0]);

        $response = $this->json('POST', "/api/cave_systems/{$caveSystem->id}?_method=PUT", [
            'name' => 'Gaping Gill',
            'length' => '',          // empty, as the form sends for an unknown/0 value
            'vertical_range' => '',
            'references' => '* [CNCC Cave Page](https://cncc.org.uk/cave/gaping-gill)',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cave_systems', [
            'id' => $caveSystem->id,
            'name' => 'Gaping Gill',
            'length' => 0,
            'vertical_range' => 0,
            'references' => '* [CNCC Cave Page](https://cncc.org.uk/cave/gaping-gill)',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_non_admins_from_updating_a_cave_system()
    {
        $nonAdminUser = User::factory()->create();
        $this->actingAs($nonAdminUser);

        $caveSystem = CaveSystem::factory()->create();

        $data = [
            'name' => 'Updated Name',
        ];

        $response = $this->json('PUT', "/api/cave_systems/{$caveSystem->id}", $data);
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_delete_files_from_cave_system()
    {
        Storage::fake('media');
        $caveSystem = CaveSystem::factory()->create();

        // Create a file attached to the system
        $file = \App\Models\CaveSystemFile::create([
            'cave_system_id' => $caveSystem->id,
            'filename' => 'todelete.pdf',
            'original_filename' => 'todelete.pdf',
            'mime_type' => 'application/pdf',
            'size' => 123,
        ]);

        // Fake the file existence on disk
        Storage::disk('media')->put("cave_system_files/{$caveSystem->id}/todelete.pdf", 'content');

        $data = [
            'name' => $caveSystem->name,
            'deleted_files' => [$file->id],
        ];

        $response = $this->json('PUT', "/api/cave_systems/{$caveSystem->id}", $data);

        $this->assertEquals(200, $response->status());

        // Check DB
        $this->assertDatabaseMissing('cave_system_files', ['id' => $file->id]);

        // Check Storage
        Storage::disk('media')->assertMissing("cave_system_files/{$caveSystem->id}/todelete.pdf");
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_cave_system_with_catchment()
    {
        $caveSystem = CaveSystem::factory()->create();
        $catchment = \App\Models\Catchment::create([
            'name' => 'Test Catchment',
            'reference_id' => 'TEST1',
            'gauges' => [],
        ]);

        $data = [
            'name' => $caveSystem->name,
            'catchment_id' => $catchment->id,
        ];

        $response = $this->putJson("/api/cave_systems/{$caveSystem->id}", $data);

        $this->assertEquals(200, $response->status());
        $response->assertJsonPath('data.catchment_id', $catchment->id);

        $this->assertEquals($catchment->id, $caveSystem->fresh()->catchment_id);
    }
}
