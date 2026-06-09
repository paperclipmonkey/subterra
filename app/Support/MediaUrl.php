<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Builds public URLs and responsive `srcset` strings for media stored on the
 * media disk.
 *
 * The GCP image processor writes three WebP variants per image, named
 * `{base}_desktop.webp`, `{base}_tablet.webp` and `{base}_mobile.webp`. The
 * stored `filename` always points at the desktop variant, so we can derive the
 * smaller variants by swapping the suffix. Widths here mirror the presets in
 * gcp-image-processor/src/index.ts.
 */
class MediaUrl
{
    /** Variant suffix => intended max width (must match the GCP processor). */
    public const VARIANT_WIDTHS = [
        'mobile' => 480,
        'tablet' => 768,
        'desktop' => 1920,
    ];

    private const DESKTOP_SUFFIX = '_desktop.webp';

    /**
     * Base URL for the media disk (no trailing slash).
     */
    public static function base(): string
    {
        return rtrim(Storage::disk('media')->url(''), '/');
    }

    /**
     * Absolute URL for a stored filename. Pass a pre-computed $base to avoid a
     * Storage::url() call per item when building large lists.
     */
    public static function url(?string $filename, ?string $base = null): ?string
    {
        if (!$filename) {
            return null;
        }

        if (str_starts_with($filename, 'http')) {
            return $filename;
        }

        $base ??= self::base();

        return $base.'/'.ltrim($filename, '/');
    }

    /**
     * Responsive srcset for a desktop-variant filename, or null when the
     * filename doesn't follow the multi-variant convention (e.g. legacy images
     * not yet re-processed, or external http URLs).
     */
    public static function srcset(?string $filename, ?string $base = null): ?string
    {
        if (!$filename || str_starts_with($filename, 'http') || !str_ends_with($filename, self::DESKTOP_SUFFIX)) {
            return null;
        }

        $stem = substr($filename, 0, -strlen(self::DESKTOP_SUFFIX));
        $base ??= self::base();

        $entries = [];
        foreach (self::VARIANT_WIDTHS as $name => $width) {
            $entries[] = $base.'/'.ltrim($stem, '/').'_'.$name.'.webp '.$width.'w';
        }

        return implode(', ', $entries);
    }

    /**
     * Convenience: a media object with both url and srcset.
     *
     * @return array{url: string|null, srcset: string|null}|null
     */
    public static function object(?string $filename, ?string $base = null): ?array
    {
        if (!$filename) {
            return null;
        }

        $base ??= self::base();

        return [
            'url' => self::url($filename, $base),
            'srcset' => self::srcset($filename, $base),
        ];
    }
}
