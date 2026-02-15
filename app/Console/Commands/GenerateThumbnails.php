<?php

namespace App\Console\Commands;

use App\Models\CaveSystemFile;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-thumbnails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate thumbnails for existing cave system files';

    /**
     * Execute the console command.
     */
    public function handle(ImageProcessingService $imageProcessingService)
    {
        $files = CaveSystemFile::whereNull('thumbnail_filename')->get();
        $count = $files->count();

        $this->info("Found {$count} files without thumbnails.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($files as $file) {
            try {
                // Construct the full path to the file
                $path = "cave_system_files/{$file->cave_system_id}/{$file->filename}";

                // Check if file exists in storage
                if (!Storage::disk('media')->exists($path)) {
                    $this->error("File not found: {$path}");
                    continue;
                }

                $content = Storage::disk('media')->get($path);
                if (empty($content)) {
                    $this->error("File content is empty: {$path}");
                    continue;
                }

                $extension = pathinfo($file->filename, PATHINFO_EXTENSION);
                $tempBase = tempnam(sys_get_temp_dir(), 'csf_');
                // Ensure extension is not empty, fallback to original filename extension
                if (empty($extension)) {
                    $extension = pathinfo($file->original_filename, PATHINFO_EXTENSION);
                }

                $tempPath = $tempBase.'.'.$extension;
                rename($tempBase, $tempPath);

                file_put_contents($tempPath, $content);

                $this->info("Processing file ID {$file->id}: {$tempPath} (Ext: {$extension})");

                $fileWrapper = new class ($tempPath, $file->mime_type) {
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

                // So we pass the relative path on the disk as "destinationPath"
                $thumbnailFilename = $imageProcessingService->generateThumbnail($fileWrapper, $path);

                if ($thumbnailFilename) {
                    $file->update(['thumbnail_filename' => $thumbnailFilename]);
                } else {
                    $this->error("Thumbnail generation returned null for file ID {$file->id}");
                }

                // Cleanup temp file
                unlink($tempPath);
            } catch (\Exception $e) {
                $this->error("Failed to generate thumbnail for file ID {$file->id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Thumbnail generation complete.');
    }
}
