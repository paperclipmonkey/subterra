<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageProcessingService
{
    public function processAndStoreImage(array $imageData, string $directory, string $suffix = ''): string
    {
        $fileData = explode(',', $imageData['data']);
        $image = Image::read($fileData[1])->scaleDown(2048, 2048)->encode(new \Intervention\Image\Encoders\WebpEncoder(quality: 65));

        $filename = Str::uuid();
        if ($suffix) {
            $filename .= '_' . $suffix;
        }
        $filePath = $directory . '/' . $filename . '.webp';

        Storage::disk('media')->put($filePath, (string) $image);

        return $filePath;
    }
}