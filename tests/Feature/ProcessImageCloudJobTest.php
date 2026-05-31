<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessImageCloudJob;
use App\Models\Cave;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImageCloudJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Storage::fake('s3_clone');
        Storage::fake('gcs_staging');
        config(['filesystems.disks.gcs_staging.bucket' => 'test-bucket']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dispatches_process_image_cloud_job_on_trip_creation()
    {
        Bus::fake([ProcessImageCloudJob::class]);

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

        // Job should have been dispatched
        Bus::assertDispatched(ProcessImageCloudJob::class, function ($job) use ($trip) {
            return $job->mediaId === $trip->media->first()->id && $job->mediaModel === \App\Models\TripMedia::class;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dispatches_process_image_cloud_job_on_trip_update()
    {
        Bus::fake([ProcessImageCloudJob::class]);

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

        Bus::assertDispatched(ProcessImageCloudJob::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_image_cloud_job_streams_file_to_gcs()
    {
        $rawPath = 'trip/test-raw.png';
        Storage::disk('s3_clone')->put($rawPath, 'fake content');

        $trip = Trip::factory()->create();
        $media = $trip->media()->create([
            'filename' => $rawPath,
        ]);

        $job = new ProcessImageCloudJob($rawPath, \App\Models\TripMedia::class, $media->id);
        $job->handle();

        // Should have streamed to gcs_staging disk
        $files = Storage::disk('gcs_staging')->allFiles('input');
        $this->assertCount(1, $files);
    }

    private function getCreatedMediaId()
    {
        return \App\Models\TripMedia::first()->id;
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
