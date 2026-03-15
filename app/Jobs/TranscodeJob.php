<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!config('services.gcp.media_processing_enabled', true)) {
            Log::info('TranscodeJob: media processing is disabled, keeping raw file.');

            return;
        }

        $inputKey = 'input/'.Str::uuid().'/'.basename($this->filePath);

        // Stream the source file from the S3-compatible disk to GCS staging — no full-file buffering
        $sourceStream = Storage::disk('s3_clone')->readStream($this->filePath);
        if (!$sourceStream) {
            throw new \RuntimeException("TranscodeJob: failed to read source file '{$this->filePath}' from s3_clone disk.");
        }

        $outputDir = 'output/'.Str::uuid().'/';

        $written = Storage::disk('gcs_staging')->put($inputKey, $sourceStream, [
            'metadata' => [
                'media_model' => Str::snake(class_basename($this->mediaModel)),
                'media_id' => (string) $this->mediaId,
                'output_prefix' => $outputDir,
                'file_path' => $this->filePath,
            ],
        ]);

        if (is_resource($sourceStream)) {
            fclose($sourceStream);
        }

        if (!$written) {
            throw new \RuntimeException("TranscodeJob: failed to write '{$inputKey}' to gcs_staging disk.");
        }

        Log::info('TranscodeJob: video uploaded to GCS staging, awaiting trigger', [
            'media_model' => $this->mediaModel,
            'media_id' => $this->mediaId,
            'input_key' => $inputKey,
        ]);
    }
}
