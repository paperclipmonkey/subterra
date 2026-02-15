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
        $fileData = explode(',', $imageData['data']);
        $image = Image::read($fileData[1])->scaleDown(1500, 1500)->encode(new WebpEncoder(quality: 60));

        $filename = Str::uuid();
        if ($suffix) {
            $filename .= '_'.$suffix;
        }
        $filePath = $directory.'/'.$filename.'.webp';

        Storage::disk('media')->put($filePath, (string) $image);

        return $filePath;
    }

    public function generateThumbnail($file, string $destinationPath): ?string
    {
        try {
            if ($file->getMimeType() === 'application/pdf') {
                $manager = new ImageManager(new ImagickDriver());
                $image = $manager->read($file->getPathname());
            } else {
                $image = Image::read($file->getPathname());
            }

            // Create thumbnail
            $thumbnail = $image->scaleDown(300, 300)->encode(new WebpEncoder(quality: 60));

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

            return $thumbnailFilename;
        } catch (\Exception $e) {
            // Log error or silently fail?
            // For now, let's log it out for debugging purposes if needed, otherwise return null
            report($e);

            return null;
        }
    }
}
