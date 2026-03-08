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
        $media = TripMedia::find($this->mediaId);
        if (!$media) {
            Log::warning("ProcessImageJob: TripMedia {$this->mediaId} not found, skipping.");

            return;
        }

        $tempFile = null;
        try {
            // Download the original file from S3 to a temp file
            $contents = Storage::disk('media')->get($this->originalPath);
            if (!$contents) {
                Log::error("ProcessImageJob: Could not read {$this->originalPath} from S3.");

                return;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tempFile, $contents);
            unset($contents);

            // Process: scale down and encode as WebP
            $image = Image::read($tempFile)->scaleDown(1500, 1500)->encode(new WebpEncoder(quality: 60));

            // Save processed version with a new .webp filename in the same directory
            $processedPath = $this->directory.'/'.Str::uuid().'.webp';
            Storage::disk('media')->put($processedPath, (string) $image);
            unset($image);

            // Update the DB record to point to the processed file
            $media->update(['filename' => $processedPath]);

            // Delete the original raw file from S3
            Storage::disk('media')->delete($this->originalPath);

            @unlink($tempFile);
            Log::info("ProcessImageJob: Processed media {$this->mediaId}");
        } catch (\Exception $e) {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            Log::error("ProcessImageJob failed for media {$this->mediaId}: ".$e->getMessage());
            throw $e; // Re-throw for queue retry
        }
    }
}
