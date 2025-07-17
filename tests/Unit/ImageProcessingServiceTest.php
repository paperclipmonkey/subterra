<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ImageProcessingService;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

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
}