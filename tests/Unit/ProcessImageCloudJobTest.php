<?php

namespace Tests\Unit;

use App\Jobs\ProcessImageCloudJob;
use App\Models\CaveMedia;
use App\Models\TripMedia;
use Illuminate\Support\Facades\Http;
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
    public function it_streams_image_to_gcs_and_submits_processing_request(): void
    {
        Storage::disk('s3_clone')->put('caves/photo.jpg', 'fake-image-content');

        Http::fake([
            '*/process' => Http::response(['status' => 'accepted', 'mediaId' => 1], 200),
        ]);

        config([
            'services.gcp.image_processor_url' => 'https://image-processor.run.app',
            'services.gcp.webhook_secret' => 'test-key',
            'filesystems.disks.gcs_staging.bucket' => 'test-staging-bucket',
            'app.url' => 'https://subterra.test',
        ]);

        $job = \Mockery::mock(ProcessImageCloudJob::class, ['caves/photo.jpg', CaveMedia::class, 1])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('getApiKey')->once()->andReturn('test-key');

        $job->handle();

        // File was written to GCS staging
        $this->assertNotEmpty(Storage::disk('gcs_staging')->allFiles('input'));

        // Cloud Run was called with the correct structure
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/process')
                && $request['bucket'] === 'test-staging-bucket'
                && str_contains($request['path'], 'input/')
                && $request['callbackUrl'] === 'https://subterra.test/api/webhooks/gcp/image-processor'
                && $request['mediaModel'] === 'cave_media'
                && $request['mediaId'] === 1;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_when_source_stream_fails(): void
    {
        // Don't put any file — readStream will return false
        config([
            'services.gcp.image_processor_url' => 'https://image-processor.run.app',
            'filesystems.disks.gcs_staging.bucket' => 'test-bucket',
        ]);

        $job = new ProcessImageCloudJob('missing/file.jpg', TripMedia::class, 99);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed to read source file');
        $job->handle();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_when_image_processor_url_is_not_configured(): void
    {
        Storage::disk('s3_clone')->put('caves/photo.jpg', 'content');

        config([
            'services.gcp.image_processor_url' => null,
            'filesystems.disks.gcs_staging.bucket' => 'test-bucket',
        ]);

        $job = new ProcessImageCloudJob('caves/photo.jpg', CaveMedia::class, 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GCP_IMAGE_PROCESSOR_URL is not configured');
        $job->handle();
    }
}
