<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessImageCloudJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;
    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  string  $filePath    Path of the source image on the media disk.
     * @param  string  $mediaModel  Fully-qualified class name of the media model.
     * @param  int     $mediaId     Primary key of the media record.
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $mediaModel,
        public readonly int $mediaId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $inputKey = 'input/'.Str::uuid().'/'.basename($this->filePath);

        // Stream the source image from S3 to GCS staging — no full-file buffering
        $sourceStream = Storage::disk('s3_clone')->readStream($this->filePath);
        if (!$sourceStream) {
            throw new \RuntimeException("ProcessImageCloudJob: failed to read source file '{$this->filePath}' from s3_clone disk.");
        }

        $written = Storage::disk('gcs_staging')->writeStream($inputKey, $sourceStream);
        if (is_resource($sourceStream)) {
            fclose($sourceStream);
        }

        if (!$written) {
            throw new \RuntimeException("ProcessImageCloudJob: failed to write '{$inputKey}' to gcs_staging disk.");
        }

        $bucket = config('filesystems.disks.gcs_staging.bucket');
        $outputPrefix = 'output/'.Str::uuid().'/';

        $this->submitProcessingJob($bucket, $inputKey, $outputPrefix);
    }

    /**
     * Submit an image processing request to the Cloud Run image processor.
     */
    protected function submitProcessingJob(string $bucket, string $inputKey, string $outputPrefix): void
    {
        $processorUrl = config('services.gcp.image_processor_url');
        if (!$processorUrl) {
            throw new \RuntimeException('ProcessImageCloudJob: GCP_IMAGE_PROCESSOR_URL is not configured.');
        }

        $callbackUrl = rtrim(config('app.url'), '/').'/api/webhooks/gcp/image-processor';

        $payload = [
            'bucket' => $bucket,
            'path' => $inputKey,
            'callbackUrl' => $callbackUrl,
            'mediaModel' => Str::snake(class_basename($this->mediaModel)),
            'mediaId' => $this->mediaId,
            'outputPrefix' => $outputPrefix,
        ];

        $response = Http::withToken($this->getApiKey())
            ->timeout(30)
            ->post(rtrim($processorUrl, '/').'/process', $payload);

        if ($response->failed()) {
            Log::error('ProcessImageCloudJob: Cloud Run request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'media_id' => $this->mediaId,
            ]);
            $response->throw();
        }

        Log::info('ProcessImageCloudJob: image processing submitted', [
            'media_model' => $this->mediaModel,
            'media_id' => $this->mediaId,
        ]);
    }

    /**
     * Get the API key for the Cloud Run image processor.
     */
    protected function getApiKey(): string
    {
        return config('services.gcp.webhook_secret', '');
    }
}
