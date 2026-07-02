<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\TranscodeJob;
use App\Models\CaveMedia;
use App\Models\TripMedia;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscodeJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3_clone');
        Storage::fake('gcs_staging');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_streams_file_from_s3_clone_to_gcs_staging_for_the_eventarc_trigger(): void
    {
        // Arrange: put a fake video on the s3_clone disk
        Storage::disk('s3_clone')->put('caves/test-video.mp4', 'fake-video-content');

        $job = new TranscodeJob('caves/test-video.mp4', CaveMedia::class, 1);

        $job->handle();

        // File was written to GCS staging under input/ — the Eventarc GCS
        // trigger starts the transcode from there; no direct API call is made.
        $files = Storage::disk('gcs_staging')->allFiles('input');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('test-video.mp4', $files[0]);
        $this->assertSame('fake-video-content', Storage::disk('gcs_staging')->get($files[0]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_when_source_stream_fails(): void
    {
        // Don't put any file — readStream will return false
        $job = new TranscodeJob('missing/video.mp4', TripMedia::class, 42);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed to read source file');
        $job->handle();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_keeps_the_raw_file_when_media_processing_is_disabled(): void
    {
        Storage::disk('s3_clone')->put('caves/test-video.mp4', 'content');

        config(['services.gcp.media_processing_enabled' => false]);

        $job = new TranscodeJob('caves/test-video.mp4', CaveMedia::class, 1);

        $job->handle();

        $this->assertEmpty(Storage::disk('gcs_staging')->allFiles());
    }
}
