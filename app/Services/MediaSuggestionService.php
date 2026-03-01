<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

class MediaSuggestionService
{
    private const PENDING_DIR = 'pending_edits';

    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {
    }

    /**
     * Scan suggested data for Base64 images/files and save them to a temporary pending directory.
     * Replaces the Base64 data with the temporary file path in the returned array.
     */
    public function savePendingMedia(array $data, string $type): array
    {
        // Recursively look for hero_image, entrance_image, photo_data, or media items
        foreach ($data as $key => &$value) {
            if (in_array($key, ['hero_image', 'entrance_image', 'photo_data', 'photo_path'])) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    $value = $this->storePendingFile($value, $type, $key);
                } elseif (is_string($value)) {
                    $base64 = $this->extractBase64($value);
                    if ($base64) {
                        $value = $this->storePendingBase64($base64, $type, $key);
                    }
                } elseif (is_array($value) && isset($value['data'])) {
                    if ($value['data'] instanceof \Illuminate\Http\UploadedFile) {
                        $value['data'] = $this->storePendingFile($value['data'], $type, $key);
                    } elseif (is_string($value['data'])) {
                        $base64 = $this->extractBase64($value['data']);
                        if ($base64) {
                            $value['data'] = $this->storePendingBase64($base64, $type, $key);
                        }
                    }
                }
            } elseif ($key === 'media' && is_array($value)) {
                foreach ($value as &$mediaItem) {
                    if (isset($mediaItem['data'])) {
                        if ($mediaItem['data'] instanceof \Illuminate\Http\UploadedFile) {
                            $mediaItem['data'] = $this->storePendingFile($mediaItem['data'], $type, 'media');
                        } elseif (is_string($mediaItem['data'])) {
                            $base64 = $this->extractBase64($mediaItem['data']);
                            if ($base64) {
                                $mediaItem['data'] = $this->storePendingBase64($base64, $type, 'media');
                            }
                        }
                    }
                }
            } elseif (is_array($value)) {
                $value = $this->savePendingMedia($value, $type);
            }
        }

        return $data;
    }

    /**
     * Promotes pending media to their permanent locations.
     */
    public function promotePendingMedia(array $data, string $targetDir): array
    {
        Log::info('Promoting pending media via MediaSuggestionService', ['targetDir' => $targetDir, 'keys' => array_keys($data)]);

        foreach ($data as $key => &$value) {
            if (in_array($key, ['hero_image', 'entrance_image', 'photo_data', 'photo_path'])) {
                if (is_array($value)) {
                    // Recurse into the array (e.g., hero_image.data)
                    $value = $this->promotePendingMedia($value, $targetDir);
                } elseif (is_string($value)) {
                    // Check if it's already a pending file
                    if (str_starts_with($value, self::PENDING_DIR)) {
                        $value = $this->moveFileToPermanent($value, $targetDir);
                    } else {
                        // Fallback: Check if it's raw base64 (or JSON wrapped) that was missed
                        $base64 = $this->extractBase64($value);
                        if ($base64) {
                            Log::info("Found raw base64 data in promotePendingMedia for key: $key");
                            // Store directly to permanent
                            $storedPath = $this->storePermanentBase64($base64, $targetDir, $key);
                            if ($storedPath) {
                                $value = $storedPath;
                            } else {
                                // If storage failed, unset or set to null to avoid DB error
                                Log::warning("Failed to store fallback base64 for key: $key. Clearing value.");
                                $value = null;
                            }
                        }
                    }

                    // Safety Net: Ensure we don't save massive strings to the DB
                    if (is_string($value) && strlen($value) > 255) {
                        Log::warning("Value for key $key is too long (>255 chars) and failed to be processed. Clearing to prevent SQL truncation error.", ['key' => $key]);
                        $value = null;
                    }
                }
            } elseif ($key === 'media' && is_array($value)) {
                foreach ($value as &$mediaItem) {
                    if (isset($mediaItem['data']) && is_string($mediaItem['data'])) {
                        if (str_starts_with($mediaItem['data'], self::PENDING_DIR)) {
                            $mediaItem['data'] = $this->moveFileToPermanent($mediaItem['data'], $targetDir);
                        } else {
                            $base64 = $this->extractBase64($mediaItem['data']);
                            if ($base64) {
                                Log::info('Found raw base64 data in media item');
                                $storedPath = $this->storePermanentBase64($base64, $targetDir, 'media');
                                if ($storedPath) {
                                    $mediaItem['data'] = $storedPath;
                                } else {
                                    Log::warning('Failed to store fallback base64 for media item. Clearing.');
                                    $mediaItem['data'] = null;
                                }
                            }
                        }
                    }
                    if (isset($mediaItem['data']) && is_string($mediaItem['data']) && strlen($mediaItem['data']) > 255) {
                        Log::warning('Media item data too long (>255 chars). Clearing.');
                        $mediaItem['data'] = null;
                    }
                }
            } elseif (is_array($value)) {
                $value = $this->promotePendingMedia($value, $targetDir);
            }
        }

        return $data;
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
            $image = Image::read($fileData[1])
                ->scaleDown(1500, 1500)
                ->encode(new WebpEncoder(quality: 80));

            Storage::disk('media')->put($path, (string) $image);
            Log::info("Stored permanent image at: $path");

            return $path;
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
            if (in_array($key, ['hero_image', 'entrance_image', 'photo_data', 'photo_path']) && is_string($value) && str_starts_with($value, self::PENDING_DIR)) {
                Storage::Disk('media')->delete($value);
            } elseif ($key === 'media' && is_array($value)) {
                // ...
                foreach ($value as $mediaItem) {
                    if (isset($mediaItem['data']) && is_string($mediaItem['data']) && str_starts_with($mediaItem['data'], self::PENDING_DIR)) {
                        Storage::Disk('media')->delete($mediaItem['data']);
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

        // Use ImageProcessingService logic but change directory
        $imageData = ['data' => $base64];
        $filename = (string) Str::uuid().'_'.$key;
        $path = self::PENDING_DIR.'/'.$type.'/'.$filename.'.webp';

        // Use simple direct storage logic
        try {
            $image = Image::read($fileData[1])
                ->scaleDown(1500, 1500)
                ->encode(new WebpEncoder(quality: 60));

            Storage::disk('media')->put($path, (string) $image);

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to store pending image: '.$e->getMessage());

            return ''; // Return empty string on failure, do NOT return raw base64
        }
    }

    private function storePendingFile(\Illuminate\Http\UploadedFile $file, string $type, string $key): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
        $filename = (string) Str::uuid().'_'.$key.'.'.$extension;
        $path = self::PENDING_DIR.'/'.$type.'/'.$filename;

        Storage::disk('media')->putFileAs(self::PENDING_DIR.'/'.$type, $file, $filename);

        return $path;
    }

    private function moveFileToPermanent(string $pendingPath, string $targetDir): string
    {
        $filename = basename($pendingPath);
        $newPath = $targetDir.'/'.$filename;

        if (Storage::disk('media')->exists($pendingPath)) {
            Storage::disk('media')->move($pendingPath, $newPath);

            return $newPath;
        }

        return $pendingPath;
    }
}
