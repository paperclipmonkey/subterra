<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class MediaSuggestionService
{
    private const PENDING_DIR = 'pending_edits';

    private const MEDIA_KEYS = ['hero_image', 'entrance_image', 'photo_data', 'photo_path'];

    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {
    }

    /**
     * Scan suggested data for Base64 images/files and save them to a temporary pending directory.
     * Replaces the Base64 data with the temporary file path in the returned array.
     *
     * Suggested data is client-controlled, so any other string in a media slot (an
     * existing path on the media disk, a URL, ...) is dropped here: the only paths a
     * suggestion may carry are the pending_edits/ ones this method produced.
     */
    public function savePendingMedia(array $data, string $type): array
    {
        // Recursively look for hero_image, entrance_image, photo_data, or media items
        foreach ($data as $key => &$value) {
            if (in_array($key, self::MEDIA_KEYS, true)) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    $value = $this->storePendingFile($value, $type, $key);
                } elseif (is_string($value)) {
                    $value = $this->storePendingString($value, $type, $key);
                    if ($value === null) {
                        unset($data[$key]);
                    }
                } elseif (is_array($value) && array_key_exists('data', $value)) {
                    if ($value['data'] instanceof \Illuminate\Http\UploadedFile) {
                        $value['data'] = $this->storePendingFile($value['data'], $type, $key);
                    } elseif (is_string($value['data'])) {
                        $value['data'] = $this->storePendingString($value['data'], $type, $key);
                    } else {
                        $value['data'] = null;
                    }
                }
            } elseif ($key === 'media' && is_array($value)) {
                foreach ($value as $index => &$mediaItem) {
                    if (!is_array($mediaItem)) {
                        unset($value[$index]);
                        continue;
                    }
                    if (($mediaItem['data'] ?? null) instanceof \Illuminate\Http\UploadedFile) {
                        $mediaItem['data'] = $this->storePendingFile($mediaItem['data'], $type, 'media');
                    } elseif (is_string($mediaItem['data'] ?? null)) {
                        $mediaItem['data'] = $this->storePendingString($mediaItem['data'], $type, 'media');
                    } else {
                        $mediaItem['data'] = null;
                    }
                    // A media item without a file we stored is meaningless; drop it.
                    if ($mediaItem['data'] === null) {
                        unset($value[$index]);
                    }
                }
                unset($mediaItem);
                $value = array_values($value);
            } elseif (is_array($value)) {
                $value = $this->savePendingMedia($value, $type);
            }
        }
        unset($value);

        return $data;
    }

    /**
     * Promotes pending media to their permanent locations.
     *
     * Every media value in the result is either null or a path this method has
     * just written under $targetDir: anything that is not one of our pending
     * files (or inline base64) is discarded, so approving a suggestion can never
     * move or republish an arbitrary file already on the media disk.
     */
    public function promotePendingMedia(array $data, string $targetDir): array
    {
        Log::info('Promoting pending media via MediaSuggestionService', ['targetDir' => $targetDir, 'keys' => array_keys($data)]);

        foreach ($data as $key => &$value) {
            if (in_array($key, self::MEDIA_KEYS, true)) {
                if (is_array($value)) {
                    // e.g. hero_image => ['data' => 'pending_edits/...', 'title' => ...]
                    $value['data'] = is_string($value['data'] ?? null)
                        ? $this->promoteString($value['data'], $targetDir, $key)
                        : null;
                    // The approval must not fall back to a client-supplied path.
                    unset($value['filename']);
                } elseif (is_string($value)) {
                    $value = $this->promoteString($value, $targetDir, $key);
                    if ($value === null) {
                        unset($data[$key]);
                    }
                }
            } elseif ($key === 'media' && is_array($value)) {
                foreach ($value as $index => &$mediaItem) {
                    $promoted = is_array($mediaItem) && is_string($mediaItem['data'] ?? null)
                        ? $this->promoteString($mediaItem['data'], $targetDir, 'media')
                        : null;
                    if ($promoted === null) {
                        Log::warning('Dropping media item without a pending file from suggestion.');
                        unset($value[$index]);
                        continue;
                    }
                    $mediaItem['data'] = $promoted;
                }
                unset($mediaItem);
                $value = array_values($value);
            } elseif (is_array($value)) {
                $value = $this->promotePendingMedia($value, $targetDir);
            }
        }
        unset($value);

        return $data;
    }

    /**
     * Whether $path is one promotePendingMedia() produced for $targetDir: a single,
     * plain filename directly inside it.
     */
    public function isPromotedPath(mixed $path, string $targetDir): bool
    {
        return is_string($path)
            && !str_contains($path, '..')
            && preg_match('#^'.preg_quote($targetDir, '#').'/[A-Za-z0-9_-]+\.[A-Za-z0-9]+$#', $path) === 1;
    }

    /**
     * Turn a client-supplied media string into a pending path, or null when it is
     * not inline base64 image data.
     */
    private function storePendingString(string $value, string $type, string $key): ?string
    {
        $base64 = $this->extractBase64($value);
        if ($base64) {
            $stored = $this->storePendingBase64($base64, $type, $key);

            return $this->isPendingPath($stored) ? $stored : null;
        }

        // Even a pending_edits/ path is refused here: the only legitimate ones are
        // those written above, and accepting a client-supplied one would let a
        // submitter claim another suggestion's pending upload.
        return null;
    }

    /**
     * Move a pending file (or store inline base64) into $targetDir and return the new
     * path, or null when the value is anything else.
     */
    private function promoteString(string $value, string $targetDir, string $key): ?string
    {
        if ($this->isPendingPath($value)) {
            return $this->moveFileToPermanent($value, $targetDir);
        }

        // Fallback: raw base64 (or JSON wrapped) that was missed at submission
        $base64 = $this->extractBase64($value);
        if ($base64) {
            Log::info("Found raw base64 data in promotePendingMedia for key: $key");
            $storedPath = $this->storePermanentBase64($base64, $targetDir, $key);
            if ($storedPath !== '') {
                return $storedPath;
            }
            Log::warning("Failed to store fallback base64 for key: $key. Clearing value.");
        }

        return null;
    }

    private function extractBase64(string $value): ?string
    {
        if (str_starts_with($value, 'data:image')) {
            return $value;
        }
        // Check for JSON wrapped data: {"data":"data:image..."}
        if (str_starts_with($value, '{') || str_starts_with($value, '"')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['data']) && is_string($decoded['data']) && str_starts_with($decoded['data'], 'data:image')) {
                Log::info('Successfully extracted base64 from JSON wrapper');

                return $decoded['data'];
            }
        }

        return null;
    }

    private function storePermanentBase64(string $base64, string $targetDir, string $key): string
    {
        $fileData = explode(',', $base64);
        if (count($fileData) < 2) {
            Log::warning('Invalid base64 string provided to storePermanentBase64 (missing comma)');

            return '';
        }

        $filename = (string) Str::uuid().'_'.$key.'.webp';
        $path = $targetDir.'/'.$filename;

        try {
            try {
                $image = Image::read($this->imageProcessingService->decodeBase64Image($base64))
                    ->scaleDown(1500, 1500)
                    ->encode(new WebpEncoder(quality: 80));
            } catch (\Intervention\Image\Exceptions\DecoderException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'image' => 'The uploaded image format (e.g. HEIC) is not supported. Please upload a JPEG, PNG, or WebP image.',
                ]);
            }

            Storage::disk('media')->put($path, (string) $image);
            Log::info("Stored permanent image at: $path");

            return $path;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to store permanent image: '.$e->getMessage());

            return ''; // Return empty string on failure to avoid saving base64 to DB
        }
    }

    /**
     * Deletes pending media associated with a rejected suggestion.
     */
    public function cleanUpPendingMedia(array $data): void
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::MEDIA_KEYS, true)) {
                $path = is_array($value) ? ($value['data'] ?? null) : $value;
                if ($this->isPendingPath($path)) {
                    Storage::disk('media')->delete($path);
                }
            } elseif ($key === 'media' && is_array($value)) {
                foreach ($value as $mediaItem) {
                    if (is_array($mediaItem) && $this->isPendingPath($mediaItem['data'] ?? null)) {
                        Storage::disk('media')->delete($mediaItem['data']);
                    }
                }
            } elseif (is_array($value)) {
                $this->cleanUpPendingMedia($value);
            }
        }
    }

    private function storePendingBase64(string $base64, string $type, string $key): string
    {
        $fileData = explode(',', $base64);
        if (count($fileData) < 2) {
            return $base64;
        }

        $filename = (string) Str::uuid().'_'.$key;
        $path = self::PENDING_DIR.'/'.$type.'/'.$filename.'.webp';

        // Use simple direct storage logic
        try {
            try {
                $image = Image::read($this->imageProcessingService->decodeBase64Image($base64))
                    ->scaleDown(1500, 1500)
                    ->encode(new WebpEncoder(quality: 60));
            } catch (\Intervention\Image\Exceptions\DecoderException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'image' => 'The uploaded image format (e.g. HEIC) is not supported. Please upload a JPEG, PNG, or WebP image.',
                ]);
            }

            Storage::disk('media')->put($path, (string) $image);

            return $path;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to store pending image: '.$e->getMessage());

            return ''; // Return empty string on failure, do NOT return raw base64
        }
    }

    private const ALLOWED_UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'mp4', 'mov', 'webm'];

    /**
     * A path we created under PENDING_DIR: "pending_edits/<type>/<file>". Suggestion
     * data comes from the client, and a bare prefix check let
     * "pending_edits/../avatars/<victim>.jpg" through — Flysystem normalises it to a
     * path inside the disk, so rejecting or approving the edit deleted or moved
     * someone else's file.
     */
    private function isPendingPath(mixed $value): bool
    {
        return is_string($value)
            && !str_contains($value, '..')
            && preg_match('#^'.self::PENDING_DIR.'/[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+$#', $value) === 1;
    }

    private function storePendingFile(\Illuminate\Http\UploadedFile $file, string $type, string $key): string
    {
        // Extension from the detected content type, never the client's filename, so an
        // HTML/SVG file can't be stored in the public media bucket as-is.
        $extension = strtolower((string) $file->guessExtension());
        if (!in_array($extension, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'Only image or video files can be attached to a suggestion.',
            ]);
        }
        $filename = (string) Str::uuid().'_'.$key.'.'.$extension;
        $path = self::PENDING_DIR.'/'.$type.'/'.$filename;

        Storage::disk('media')->putFileAs(self::PENDING_DIR.'/'.$type, $file, $filename);

        return $path;
    }

    private function moveFileToPermanent(string $pendingPath, string $targetDir): ?string
    {
        $filename = basename($pendingPath);
        $newPath = $targetDir.'/'.$filename;

        if (Storage::disk('media')->exists($pendingPath)) {
            Storage::disk('media')->move($pendingPath, $newPath);

            return $newPath;
        }

        return null;
    }
}
