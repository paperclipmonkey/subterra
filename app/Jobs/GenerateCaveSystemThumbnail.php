<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CaveSystemFile;
use App\Services\ImageProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateCaveSystemThumbnail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CaveSystemFile $file
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(ImageProcessingService $imageProcessingService): void
    {
        $path = "cave_system_files/{$this->file->cave_system_id}/{$this->file->filename}";

        if (!Storage::disk('media')->exists($path)) {
            \Log::error("File not found for thumbnail generation: {$path}");

            return;
        }

        $content = Storage::disk('media')->get($path);
        if (empty($content)) {
            \Log::error("File content is empty for thumbnail generation: {$path}");

            return;
        }

        $extension = pathinfo($this->file->filename, PATHINFO_EXTENSION);
        // Fallback extension
        if (empty($extension)) {
            $extension = pathinfo($this->file->original_filename, PATHINFO_EXTENSION);
        }

        $tempBase = tempnam(sys_get_temp_dir(), 'csf_job_');
        $tempPath = $tempBase.'.'.$extension;
        rename($tempBase, $tempPath);

        file_put_contents($tempPath, $content);

        try {
            // Create a wrapper similar to the command one
            $fileWrapper = new class ($tempPath, $this->file->mime_type) {
                private $path;
                private $mime;

                public function __construct($path, $mime)
                {
                    $this->path = $path;
                    $this->mime = $mime;
                }

                public function getMimeType()
                {
                    return $this->mime;
                }

                public function getPathname()
                {
                    return $this->path;
                }

                public function __toString()
                {
                    return $this->path;
                }
            };

            $thumbnailFilename = $imageProcessingService->generateThumbnail($fileWrapper, $path);

            if ($thumbnailFilename) {
                $this->file->update(['thumbnail_filename' => $thumbnailFilename]);
            } else {
                \Log::error("Thumbnail generation returned null for file ID {$this->file->id}");
            }
        } catch (\Exception $e) {
            \Log::error("Failed to generate thumbnail for file ID {$this->file->id}: ".$e->getMessage());
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
