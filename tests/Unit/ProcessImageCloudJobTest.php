<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ProcessImageCloudJob;
use App\Models\CaveMedia;
use App\Models\TripMedia;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImageCloudJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_clone');
        Storage::fake('gcs_staging');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_streams_image_to_gcs_staging_for_the_eventarc_trigger(): void
    {
        Storage::disk('s3_clone')->put('caves/photo.jpg', 'fake-image-content');

        $job = new ProcessImageCloudJob('caves/photo.jpg', CaveMedia::class, 1);

        $job->handle();

        // File was written to GCS staging under input/ — the Eventarc GCS
        // trigger picks it up from there; no direct API call is made.
        $files = Storage::disk('gcs_staging')->allFiles('input');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('photo.jpg', $files[0]);
        $this->assertSame('fake-image-content', Storage::disk('gcs_staging')->get($files[0]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_when_source_stream_fails(): void
    {
        // Don't put any file — readStream will return false
        $job = new ProcessImageCloudJob('missing/file.jpg', TripMedia::class, 99);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed to read source file');
        $job->handle();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_keeps_the_raw_file_when_media_processing_is_disabled(): void
    {
        Storage::disk('s3_clone')->put('caves/photo.jpg', 'content');

        config(['services.gcp.media_processing_enabled' => false]);

        $job = new ProcessImageCloudJob('caves/photo.jpg', CaveMedia::class, 1);

        $job->handle();

        $this->assertEmpty(Storage::disk('gcs_staging')->allFiles());
    }
}
