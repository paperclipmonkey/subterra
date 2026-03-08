<?php

namespace Tests\Feature;

use App\Jobs\ProcessImageJob;
use App\Models\Cave;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Tests\TestCase;

class ProcessImageJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dispatches_process_image_job_on_trip_creation()
    {
        Bus::fake([ProcessImageJob::class]);

        $user = User::factory()->create();
        $entrance = Cave::factory()->create();
        Event::fake([\App\Events\TripCreated::class]);

        $imageFile = UploadedFile::fake()->image('test.jpg');

        $tripData = [
            'name' => 'Job Dispatch Test',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$user->id],
            'media' => [
                ['data' => $imageFile],
            ],
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', $tripData);
        $response->assertCreated();

        $trip = Trip::where('name', 'Job Dispatch Test')->first();
        $this->assertCount(1, $trip->media);

        // Raw file should be on disk
        Storage::disk('media')->assertExists($trip->media->first()->filename);

        // Job should have been dispatched
        Bus::assertDispatched(ProcessImageJob::class, function ($job) use ($trip) {
            return $job->mediaId === $trip->media->first()->id;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dispatches_process_image_job_on_trip_update()
    {
        Bus::fake([ProcessImageJob::class]);

        $user = User::factory()->create();
        $entrance = Cave::factory()->create();
        $trip = Trip::factory()->create(['entrance_cave_id' => $entrance->id]);
        $trip->participants()->attach($user);

        $imageFile = UploadedFile::fake()->image('update.png');

        $updateData = [
            'name' => 'Updated Trip',
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'cave_system_id' => $entrance->cave_system_id,
            'participants' => [$user->id],
            'media' => [
                ['data' => $imageFile],
            ],
            'existing_media' => [],
            '_method' => 'PUT',
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips/'.$trip->short_id, $updateData);
        $response->assertOk();

        $trip = $trip->fresh();
        $this->assertCount(1, $trip->media);

        // Raw file should be on disk
        Storage::disk('media')->assertExists($trip->media->first()->filename);

        Bus::assertDispatched(ProcessImageJob::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_image_job_processes_and_replaces_raw_file()
    {
        // Upload a raw image to the fake disk
        $rawPath = 'trip/test-raw.png';
        $pngContent = file_get_contents(__DIR__.'/../../Fixtures/test.png');
        Storage::disk('media')->put($rawPath, $pngContent);

        // Create a trip and media record
        $trip = Trip::factory()->create();
        $media = $trip->media()->create([
            'filename' => $rawPath,
        ]);

        // Run the job
        $job = new ProcessImageJob($media->id, $rawPath, 'trip');
        $job->handle();

        $media->refresh();

        // The filename should have changed to a .webp
        $this->assertStringEndsWith('.webp', $media->filename);
        $this->assertNotEquals($rawPath, $media->filename);

        // Processed file should exist, raw file should be deleted
        Storage::disk('media')->assertExists($media->filename);
        Storage::disk('media')->assertMissing($rawPath);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_image_job_handles_missing_media_gracefully()
    {
        $rawPath = 'trip/nonexistent-media.png';
        Storage::disk('media')->put($rawPath, 'fake content');

        // Use a media ID that doesn't exist
        $job = new ProcessImageJob(99999, $rawPath, 'trip');
        $job->handle(); // Should not throw

        // Raw file should still exist (job skipped processing)
        Storage::disk('media')->assertExists($rawPath);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_non_image_files()
    {
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();

        $textFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $tripData = [
            'name' => 'XSS Attempt',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$user->id],
            'media' => [
                ['data' => $textFile],
            ],
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', $tripData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['media.0.data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_svg_files_which_could_contain_xss()
    {
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();

        // SVG files can contain JavaScript but aren't in the allowed mimes list
        $svgFile = UploadedFile::fake()->create('evil.svg', 100, 'image/svg+xml');

        $tripData = [
            'name' => 'SVG XSS attempt',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$user->id],
            'media' => [
                ['data' => $svgFile],
            ],
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', $tripData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['media.0.data']);
    }
}
