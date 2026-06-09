<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
     * @param  string       $filePath    Path of the source image on the media disk; its bytes are processed and it is preserved as the original.
     * @param  string       $mediaModel  Fully-qualified class name of the media model.
     * @param  int          $mediaId     Primary key of the media record.
     * @param  string|null  $namingBase  Logical path used to name the generated variants ({base}_desktop.webp, etc). Defaults to $filePath. Pass a variant-suffix-stripped path when re-processing an already-processed image so variants don't gain a double suffix.
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $mediaModel,
        public readonly int $mediaId,
        public readonly ?string $namingBase = null,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!config('services.gcp.media_processing_enabled', true)) {
            Log::info('ProcessImageCloudJob: media processing is disabled, keeping raw file.');

            return;
        }

        $inputKey = 'input/'.Str::uuid().'/'.basename($this->filePath);

        // Stream the source image from S3 to GCS staging — no full-file buffering
        $sourceStream = Storage::disk('s3_clone')->readStream($this->filePath);
        if (!$sourceStream) {
            throw new \RuntimeException("ProcessImageCloudJob: failed to read source file '{$this->filePath}' from s3_clone disk.");
        }

        $outputPrefix = 'output/'.Str::uuid().'/';

        $written = Storage::disk('gcs_staging')->put($inputKey, $sourceStream, [
            'metadata' => [
                'metadata' => [
                    'media_model' => Str::snake(class_basename($this->mediaModel)),
                    'media_id' => (string) $this->mediaId,
                    'output_prefix' => $outputPrefix,
                    'file_path' => $this->filePath,
                    'naming_base' => $this->namingBase ?? $this->filePath,
                ],
            ],
        ]);

        if (is_resource($sourceStream)) {
            fclose($sourceStream);
        }

        if (!$written) {
            throw new \RuntimeException("ProcessImageCloudJob: failed to write '{$inputKey}' to gcs_staging disk.");
        }

        Log::info('ProcessImageCloudJob: image uploaded to GCS staging, awaiting trigger', [
            'media_model' => $this->mediaModel,
            'media_id' => $this->mediaId,
            'input_key' => $inputKey,
        ]);
    }
}
