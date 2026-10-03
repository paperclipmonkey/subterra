<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageProcessingServiceTest extends TestCase
{
    public function test_imagick_driver_is_configured()
    {
        // Verify that the configuration change is effective
        $config = config('image.driver');
        $this->assertEquals(\Intervention\Image\Drivers\Imagick\Driver::class, $config);
    }

    public function test_imagick_supports_heic_format()
    {
        // Verify that ImageMagick has HEIC support
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ImageMagick extension not loaded');
        }

        $imagick = new \Imagick();
        $formats = $imagick->queryFormats();
        $this->assertContains('HEIC', $formats, 'ImageMagick should support HEIC format');
    }

    public function test_image_processing_service_instantiates()
    {
        // Verify the service can be instantiated
        $service = new ImageProcessingService();
        $this->assertInstanceOf(ImageProcessingService::class, $service);
    }

    private static function pngBytes(): string
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private static function fileWrapper(string $path, string $mime): object
    {
        return new class ($path, $mime) {
            public function __construct(private string $path, private string $mime)
            {
            }

            public function getMimeType(): string
            {
                return $this->mime;
            }

            public function getPathname(): string
            {
                return $this->path;
            }
        };
    }

    #[Test]
    public function raster_allowlist_accepts_png(): void
    {
        (new ImageProcessingService())->assertSupportedRasterImage(self::pngBytes());
        $this->addToAssertionCount(1);
    }

    public static function nonRasterPayloads(): array
    {
        return [
            'empty' => [''],
            'pdf' => ["%PDF-1.4\n%%EOF\n"],
            'postscript' => ["%!PS-Adobe-3.0\nshowpage\n"],
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            'file path' => ['/etc/hostname'],
        ];
    }

    #[Test]
    #[DataProvider('nonRasterPayloads')]
    public function raster_allowlist_rejects_other_content(string $bytes): void
    {
        $this->expectException(ValidationException::class);
        (new ImageProcessingService())->assertSupportedRasterImage($bytes);
    }

    #[Test]
    public function base64_decoding_is_strict(): void
    {
        $this->expectException(ValidationException::class);
        (new ImageProcessingService())->decodeBase64Image('data:image/png;base64,***'.base64_encode(self::pngBytes()));
    }

    #[Test]
    public function thumbnail_refuses_a_pdf_whose_stored_mime_claims_an_image(): void
    {
        Storage::fake('media');
        $path = tempnam(sys_get_temp_dir(), 'thumb');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        try {
            $this->expectException(ValidationException::class);
            (new ImageProcessingService())->generateThumbnail(self::fileWrapper($path, 'image/png'), 'cave_system_files/1/x.png');
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function thumbnail_does_not_take_the_pdf_branch_for_non_pdf_content(): void
    {
        if (!extension_loaded('imagick')) {
            config(['image.driver' => \Intervention\Image\Drivers\Gd\Driver::class]);
        }
        Storage::fake('media');
        $path = tempnam(sys_get_temp_dir(), 'thumb');
        file_put_contents($path, self::pngBytes());

        try {
            // A client-claimed application/pdf with PNG content is treated as a raster.
            $thumb = (new ImageProcessingService())->generateThumbnail(self::fileWrapper($path, 'application/pdf'), 'cave_system_files/1/x.pdf');
        } finally {
            @unlink($path);
        }

        $this->assertSame('x_thumb.webp', $thumb);
        Storage::disk('media')->assertExists('cave_system_files/1/x_thumb.webp');
    }
}
