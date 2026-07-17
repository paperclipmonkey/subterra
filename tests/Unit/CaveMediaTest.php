<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CaveMedia;
use Tests\TestCase;

class CaveMediaTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function preview_and_poster_urls_only_replace_the_trailing_extension(): void
    {
        // The directory deliberately contains the extension substring: a naive
        // str_replace on the extension would corrupt the path segment instead
        // of swapping the file extension.
        $media = new CaveMedia([
            'type' => 'hero_video',
            'filename' => 'caves/mp4-collection/clip.mp4',
        ]);

        $this->assertStringEndsWith('caves/mp4-collection/clip.webm', $media->preview_url);
        $this->assertStringEndsWith('caves/mp4-collection/clip.webp', $media->poster_url);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function preview_and_poster_urls_are_null_for_non_video_media(): void
    {
        $media = new CaveMedia([
            'type' => 'hero',
            'filename' => 'caves/photo.webp',
        ]);

        $this->assertNull($media->preview_url);
        $this->assertNull($media->poster_url);
    }
}
