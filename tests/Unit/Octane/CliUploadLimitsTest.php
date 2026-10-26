<?php

declare(strict_types=1);

namespace Tests\Unit\Octane;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Octane's RoadRunner workers run on the PHP CLI, so the upload limits the
 * php-fpm pool used to set must also be set for the CLI. Without them the CLI
 * default post_max_size (8M) makes ValidatePostSize reject trips with a couple
 * of phone photos with a 413.
 */
class CliUploadLimitsTest extends TestCase
{
    private const INI = '.fly/php/conf.d/99-subterra.ini';

    /** Trip photos may be up to 512000 KB each (StoreTripRequest / UpdateTripRequest). */
    private const PHOTO_LIMIT_BYTES = 512000 * 1024;

    #[Test]
    public function the_dockerfile_installs_the_ini_for_the_cli(): void
    {
        $dockerfile = (string) file_get_contents($this->path('Dockerfile'));

        $this->assertStringContainsString(
            'COPY '.self::INI.' /etc/php/${PHP_VERSION}/cli/conf.d/',
            $dockerfile,
        );
    }

    #[Test]
    public function the_cli_accepts_a_request_as_large_as_a_trip_photo(): void
    {
        $dockerfile = (string) file_get_contents($this->path('Dockerfile'));
        $ini = (string) file_get_contents($this->path(self::INI));

        // Expand ${VAR} the way PHP will, using the Dockerfile's ENV values.
        $ini = preg_replace_callback('/\$\{(\w+)\}/', function (array $m) use ($dockerfile): string {
            $this->assertMatchesRegularExpression('/\b'.$m[1].'=(\S+)/', $dockerfile, "Dockerfile does not set {$m[1]}");
            preg_match('/\b'.$m[1].'=(\S+)/', $dockerfile, $value);

            return $value[1];
        }, $ini);

        $settings = parse_ini_string((string) $ini, false, INI_SCANNER_RAW);
        $this->assertIsArray($settings);

        foreach (['post_max_size', 'upload_max_filesize'] as $key) {
            $this->assertArrayHasKey($key, $settings);
            $this->assertGreaterThanOrEqual(self::PHOTO_LIMIT_BYTES, $this->bytes($settings[$key]), $key);
        }
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 3).'/'.$relative;
    }

    private function bytes(string $size): int
    {
        $number = (int) $size;

        return match (strtoupper(substr(trim($size), -1))) {
            'K' => $number * 1024,
            'M' => $number * 1024 ** 2,
            'G' => $number * 1024 ** 3,
            default => $number,
        };
    }
}
