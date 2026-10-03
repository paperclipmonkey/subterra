<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;

class ImageProcessingService
{
    /**
     * Raster formats we hand to ImageMagick. Everything else is refused before it
     * reaches a decoder: ImageMagick picks its coder from the content's magic bytes,
     * not the declared type, so a "PNG" that is really a PDF, PostScript, SVG, MVG or
     * TEXT payload would otherwise be rendered by Ghostscript or read local files.
     */
    public const ALLOWED_RASTER_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/heic',
        'image/heif',
        'image/avif',
    ];

    private const UNSUPPORTED_MESSAGE = 'The uploaded image format is not supported. Please upload a JPEG, PNG, or WebP image.';

    /**
     * Throws a ValidationException unless the bytes sniff as an allowlisted raster image.
     */
    public function assertSupportedRasterImage(string $bytes): void
    {
        if ($bytes === '') {
            throw ValidationException::withMessages(['image' => self::UNSUPPORTED_MESSAGE]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (!is_string($mime) || !in_array($mime, self::ALLOWED_RASTER_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['image' => self::UNSUPPORTED_MESSAGE]);
        }
    }

    /**
     * Strictly decode the payload of a base64 data URI to raw image bytes and check
     * them against the raster allowlist. Raw bytes (never a string Intervention could
     * take for a file path) are what callers then pass to Image::read().
     */
    public function decodeBase64Image(string $base64DataUri): string
    {
        $parts = explode(',', $base64DataUri, 2);
        if (count($parts) !== 2) {
            throw ValidationException::withMessages([
                'image' => 'Invalid image data.',
            ]);
        }

        $bytes = base64_decode($parts[1], true);
        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages([
                'image' => 'Invalid image data.',
            ]);
        }

        $this->assertSupportedRasterImage($bytes);

        return $bytes;
    }

    /**
     * Read a local file's bytes and check them against the raster allowlist.
     */
    private function readSupportedRasterFile(string $path): string
    {
        $bytes = is_file($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            throw ValidationException::withMessages(['image' => self::UNSUPPORTED_MESSAGE]);
        }

        $this->assertSupportedRasterImage($bytes);

        return $bytes;
    }

    public function processAndStoreImage(array $imageData, string $directory, string $suffix = ''): string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $imageData['data'];

        $bytes = $this->readSupportedRasterFile($file->getPathname());

        try {
            $image = Image::read($bytes)->scaleDown(1500, 1500)->encode(new WebpEncoder(quality: 60));
        } catch (\Intervention\Image\Exceptions\DecoderException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'The uploaded image format (e.g. HEIC) is not supported. Please upload a JPEG, PNG, or WebP image.',
            ]);
        }
        unset($bytes);

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

    public function processAndStoreBase64Image(string $base64DataUri, string $directory, string $suffix = ''): string
    {
        $binaryData = $this->decodeBase64Image($base64DataUri);

        try {
            $image = Image::read($binaryData)->scaleDown(1500, 1500)->encode(new WebpEncoder(quality: 60));
        } catch (\Intervention\Image\Exceptions\DecoderException $e) {
            throw ValidationException::withMessages([
                'image' => 'The uploaded image format is not supported. Please upload a JPEG, PNG, or WebP image.',
            ]);
        }

        $filename = Str::uuid();
        if ($suffix) {
            $filename .= '_'.$suffix;
        }
        $filePath = $directory.'/'.$filename.'.webp';

        Storage::disk('media')->put($filePath, (string) $image);

        unset($image);
        gc_collect_cycles();

        return $filePath;
    }

    public function generateThumbnail($file, string $destinationPath): ?string
    {
        \Log::info("Generating thumbnail for {$file->getPathname()}. Initial memory: ".round(memory_get_usage() / 1024 / 1024, 2).'MB');
        try {
            // The stored mime type alone is not enough to take the Ghostscript path:
            // the content itself must be detected as a PDF by the server. Anything
            // else goes through the raster allowlist.
            $detectedMime = is_file($file->getPathname())
                ? (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname())
                : false;

            if ($file->getMimeType() === 'application/pdf' && $detectedMime === 'application/pdf') {
                $manager = new ImageManager(new ImagickDriver());
                // Force reading only the first page to save memory
                $image = $manager->read($file->getPathname().'[0]');
            } else {
                $image = Image::read($this->readSupportedRasterFile($file->getPathname()));
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

        $allowedMimeTypes = ['video/mp4', 'video/quicktime', 'video/webm'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'video' => 'Unsupported video format. Allowed formats: MP4, QuickTime, WebM.',
            ]);
        }

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
