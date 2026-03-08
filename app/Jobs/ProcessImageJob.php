<?php

namespace App\Jobs;

use App\Models\TripMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class ProcessImageJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 120;
    public $tries = 3;

    public function __construct(
        public readonly int $mediaId,
        public readonly string $originalPath,
        public readonly string $directory
    ) {
    }

    public function handle(): void
    {
        // Allow enough memory for large images (e.g. 6000x4000 = ~96MB uncompressed)
        ini_set('memory_limit', '512M');

        $media = TripMedia::find($this->mediaId);
        if (!$media) {
            Log::warning("ProcessImageJob: TripMedia {$this->mediaId} not found, skipping.");

            return;
        }

        $tempFile = null;
        try {
            // Stream the file from S3 to a temp file (avoids loading entire file into PHP memory)
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            $stream = Storage::disk('media')->readStream($this->originalPath);
            if (!$stream) {
                Log::error("ProcessImageJob: Could not read {$this->originalPath} from S3.");

                return;
            }
            file_put_contents($tempFile, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // Process: scale down and encode as WebP
            $image = Image::read($tempFile)->scaleDown(1500, 1500)->encode(new WebpEncoder(quality: 60));

            // Free the temp file immediately — we don't need it after Intervention has read it
            @unlink($tempFile);
            $tempFile = null;

            // Save processed version with a new .webp filename in the same directory
            $processedPath = $this->directory.'/'.Str::uuid().'.webp';
            Storage::disk('media')->put($processedPath, (string) $image);
            unset($image);
            gc_collect_cycles();

            // Update the DB record to point to the processed file
            $media->update(['filename' => $processedPath]);

            // Delete the original raw file from S3
            Storage::disk('media')->delete($this->originalPath);

            Log::info("ProcessImageJob: Processed media {$this->mediaId}");
        } catch (\Throwable $e) {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            Log::error("ProcessImageJob failed for media {$this->mediaId}: ".$e->getMessage());
            throw $e;
        }
    }
}
