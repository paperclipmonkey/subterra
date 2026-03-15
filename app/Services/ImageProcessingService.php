<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;

class ImageProcessingService
{
    public function processAndStoreImage(array $imageData, string $directory, string $suffix = ''): string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $imageData['data'];

        try {
            $image = Image::read($file->getPathname())->scaleDown(1500, 1500)->encode(new WebpEncoder(quality: 60));
        } catch (\Intervention\Image\Exceptions\DecoderException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'The uploaded image format (e.g. HEIC) is not supported. Please upload a JPEG, PNG, or WebP image.',
            ]);
        }

        $filename = Str::uuid();
        if ($suffix) {
            $filename .= '_'.$suffix;
        }
        $filePath = $directory.'/'.$filename.'.webp';

        Storage::disk('media')->put($filePath, (string) $image);

        unset($image); // Free memory immediately
        gc_collect_cycles();

        return $filePath;
    }

    public function generateThumbnail($file, string $destinationPath): ?string
    {
        \Log::info("Generating thumbnail for {$file->getPathname()}. Initial memory: ".round(memory_get_usage() / 1024 / 1024, 2).'MB');
        try {
            if ($file->getMimeType() === 'application/pdf') {
                $manager = new ImageManager(new ImagickDriver());
                // Force reading only the first page to save memory
                $image = $manager->read($file->getPathname().'[0]');
            } else {
                $image = Image::read($file->getPathname());
            }
        } catch (\Intervention\Image\Exceptions\DecoderException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'The uploaded image format (e.g. HEIC) is not supported. Please upload a JPEG, PNG, or WebP image.',
            ]);
        }

        // Create thumbnail
        $thumbnail = $image->scaleDown(300, 300)->encode(new WebpEncoder(quality: 60));
        unset($image); // Free original image memory ASAP

        // Generate filename based on destination path but with .webp extension
        $pathInfo = pathinfo($destinationPath);
        $thumbnailFilename = $pathInfo['filename'].'_thumb.webp';
        $thumbnailPath = $pathInfo['dirname'].'/'.$thumbnailFilename;

        // Remove beginning slash if present to avoid double slash issue with Storage
        if (str_starts_with($thumbnailPath, '/')) {
            $thumbnailPath = substr($thumbnailPath, 1);
        }

        // If dirname was empty or just '.', we need to be careful
        if ($pathInfo['dirname'] === '.') {
            $thumbnailPath = $thumbnailFilename;
        }

        Storage::disk('media')->put($thumbnailPath, (string) $thumbnail);
        unset($thumbnail); // Free thumbnail memory
        gc_collect_cycles(); // Force garbage collection

        return $thumbnailFilename;
    }

    public function processAndStoreVideo(array $videoData, string $directory, string $suffix = ''): string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $videoData['data'];

        $filename = Str::uuid();
        if ($suffix) {
            $filename .= '_'.$suffix;
        }
        $originalFilename = $filename.'.mp4';
        $originalPath = $directory.'/'.$originalFilename;

        Storage::disk('media')->putFileAs($directory, $file, $originalFilename);

        return $originalPath;
    }
}
