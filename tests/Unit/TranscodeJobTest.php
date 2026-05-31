<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\TranscodeJob;
use App\Models\CaveMedia;
use App\Models\TripMedia;
use Illuminate\Support\Facades\Http;
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
    public function it_streams_file_from_s3_clone_to_gcs_staging_and_calls_transcoder_api(): void
    {
        // Arrange: put a fake video on the s3_clone disk
        Storage::disk('s3_clone')->put('caves/test-video.mp4', 'fake-video-content');

        Http::fake([
            'https://transcoder.googleapis.com/*' => Http::response(['name' => 'projects/p/locations/l/jobs/123'], 200),
        ]);

        config([
            'services.gcp.project_id' => 'test-project',
            'services.gcp.location' => 'europe-west2',
            'filesystems.disks.gcs_staging.bucket' => 'test-staging-bucket',
        ]);

        // Partial mock so we bypass the real ADC token fetch
        $job = \Mockery::mock(TranscodeJob::class, ['caves/test-video.mp4', CaveMedia::class, 1])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('getAccessToken')->once()->andReturn('fake-token');

        $job->handle();

        // Assert: file was written to gcs_staging
        $this->assertNotEmpty(Storage::disk('gcs_staging')->allFiles('input'));

        // Assert: Transcoder API was called with the correct structure
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'transcoder.googleapis.com')
                && $request['templateId'] === 'web-hd-mp4'
                && str_contains($request['inputUri'], 'gs://test-staging-bucket/')
                && str_contains($request['outputUri'], 'gs://test-staging-bucket/')
                && $request['labels']['media_model'] === 'cave_media'
                && $request['labels']['media_id'] === '1';
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stores_correct_labels_in_transcoder_job(): void
    {
        Storage::disk('s3_clone')->put('trips/trip-video.mp4', 'fake-content');

        Http::fake([
            'https://transcoder.googleapis.com/*' => Http::response(['name' => 'projects/p/locations/l/jobs/456'], 200),
        ]);

        config([
            'services.gcp.project_id' => 'test-project',
            'services.gcp.location' => 'europe-west2',
            'filesystems.disks.gcs_staging.bucket' => 'test-staging-bucket',
        ]);

        $job = \Mockery::mock(TranscodeJob::class, ['trips/trip-video.mp4', TripMedia::class, 42])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('getAccessToken')->once()->andReturn('fake-token');

        $job->handle();

        Http::assertSent(function ($request) {
            $labels = $request['labels'];

            return $labels['media_model'] === 'trip_media'
                && $labels['media_id'] === '42'
                && !empty($labels['output_dir'])
                && !empty($labels['input_prefix']);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_pubsub_topic_in_config_when_configured(): void
    {
        Storage::disk('s3_clone')->put('caves/video.mp4', 'content');

        Http::fake([
            'https://transcoder.googleapis.com/*' => Http::response(['name' => 'projects/p/locations/l/jobs/789'], 200),
        ]);

        config([
            'services.gcp.project_id' => 'test-project',
            'services.gcp.location' => 'europe-west2',
            'services.gcp.transcoder_pubsub_topic' => 'projects/test-project/topics/transcoder-notifications',
            'filesystems.disks.gcs_staging.bucket' => 'test-staging-bucket',
        ]);

        $job = \Mockery::mock(TranscodeJob::class, ['caves/video.mp4', CaveMedia::class, 5])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('getAccessToken')->once()->andReturn('fake-token');

        $job->handle();

        Http::assertSent(function ($request) {
            return $request['config']['pubsubDestination']['topic'] === 'projects/test-project/topics/transcoder-notifications';
        });
    }
}
