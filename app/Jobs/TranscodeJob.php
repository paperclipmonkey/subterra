<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscodeJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;
    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  string  $filePath    Path of the source video on the s3_clone disk.
     * @param  string  $mediaModel  Fully-qualified class name of the media model to update on completion.
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

        // Stream the source file from the S3-compatible disk to GCS staging — no full-file buffering
        $sourceStream = Storage::disk('s3_clone')->readStream($this->filePath);
        if (!$sourceStream) {
            throw new \RuntimeException("TranscodeJob: failed to read source file '{$this->filePath}' from s3_clone disk.");
        }

        $written = Storage::disk('gcs_staging')->writeStream($inputKey, $sourceStream);
        if (is_resource($sourceStream)) {
            fclose($sourceStream);
        }

        if (!$written) {
            throw new \RuntimeException("TranscodeJob: failed to write '{$inputKey}' to gcs_staging disk.");
        }

        $bucket = config('filesystems.disks.gcs_staging.bucket');
        $outputDir = 'output/'.Str::uuid().'/';

        $this->submitTranscoderJob(
            inputUri: "gs://{$bucket}/{$inputKey}",
            outputUri: "gs://{$bucket}/{$outputDir}",
            outputDir: $outputDir,
            inputKey: $inputKey,
        );
    }

    /**
     * Submit a job to the GCP Transcoder API using the web-hd-mp4 template.
     */
    protected function submitTranscoderJob(string $inputUri, string $outputUri, string $outputDir, string $inputKey): void
    {
        $projectId = config('services.gcp.project_id');
        $location = config('services.gcp.location');

        $payload = [
            'inputUri' => $inputUri,
            'outputUri' => $outputUri,
            'templateId' => 'web-hd-mp4',
            'labels' => [
                'media_model' => Str::snake(class_basename($this->mediaModel)),
                'media_id' => (string) $this->mediaId,
                'output_dir' => base64_encode($outputDir),
                'input_prefix' => base64_encode(dirname($inputKey).'/'),
            ],
        ];

        $pubsubTopic = config('services.gcp.transcoder_pubsub_topic');
        if ($pubsubTopic) {
            $payload['config']['pubsubDestination']['topic'] = $pubsubTopic;
        }

        $response = Http::withToken($this->getAccessToken())
            ->post(
                "https://transcoder.googleapis.com/v1/projects/{$projectId}/locations/{$location}/jobs",
                $payload
            );

        if ($response->failed()) {
            Log::error('TranscodeJob: GCP Transcoder API call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'media_id' => $this->mediaId,
            ]);
            $response->throw();
        }

        Log::info('TranscodeJob: Transcoder job submitted', [
            'job_name' => $response->json('name'),
            'media_id' => $this->mediaId,
        ]);
    }

    /**
     * Obtain a short-lived Google OAuth2 access token using Application Default Credentials.
     */
    protected function getAccessToken(): string
    {
        $credentials = \Google\Auth\ApplicationDefaultCredentials::getCredentials(
            'https://www.googleapis.com/auth/cloud-platform'
        );

        $token = $credentials->fetchAuthToken(
            \Google\Auth\HttpHandler\HttpHandlerFactory::build()
        );

        return $token['access_token'];
    }
}
