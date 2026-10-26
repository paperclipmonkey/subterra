<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Hut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Image endpoints must only ever hand allowlisted raster bytes to the image
 * library. ImageMagick picks its coder from magic bytes, so a "data:image/png"
 * that is really a PDF/PS/SVG would reach Ghostscript, and Intervention's
 * Image::read() also accepts a file path, which would copy server-local files.
 */
class ImageUploadContentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');

        // CI and the devcontainer have Imagick; fall back to GD where it is missing
        // so the "valid PNG still works" cases can run anywhere.
        if (!extension_loaded('imagick')) {
            config(['image.driver' => \Intervention\Image\Drivers\Gd\Driver::class]);
        }
    }

    public static function maliciousPayloads(): array
    {
        return [
            'pdf' => ["%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n"],
            'postscript' => ["%!PS-Adobe-3.0\n/Helvetica findfont 12 scalefont setfont\nshowpage\n"],
            'svg' => ['<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><image href="file:///etc/passwd"/></svg>'],
            'mvg' => ["push graphic-context\nviewbox 0 0 640 480\nimage over 0,0 0,0 'file:///etc/passwd'\npop graphic-context\n"],
            'text' => ["just some text\n"],
            'server file path' => ['/etc/hostname'],
        ];
    }

    private static function pngBytes(): string
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    #[Test]
    #[DataProvider('maliciousPayloads')]
    public function collection_photo_rejects_non_raster_base64(string $payload): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/collections', [
            'name' => 'Sneaky',
            'photo_data' => 'data:image/png;base64,'.base64_encode($payload),
        ])->assertStatus(422);

        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    #[Test]
    #[DataProvider('maliciousPayloads')]
    public function hut_image_rejects_non_raster_base64(string $payload): void
    {
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();

        $this->actingAs($user)->postJson('/api/huts', [
            'name' => 'Sneaky Hut',
            'club_id' => $club->id,
            'image' => ['data' => 'data:image/png;base64,'.base64_encode($payload)],
        ])->assertStatus(422);

        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    #[Test]
    public function base64_encoded_server_file_path_is_rejected(): void
    {
        $user = User::factory()->create();

        // A real image on the server: Image::read() would happily load it by path
        // and the copy would land in public storage.
        $localImage = tempnam(sys_get_temp_dir(), 'img').'.png';
        file_put_contents($localImage, self::pngBytes());

        try {
            $this->actingAs($user)->postJson('/api/collections', [
                'name' => 'Path Trick',
                'photo_data' => 'data:image/png;base64,'.base64_encode($localImage),
            ])->assertStatus(422);
        } finally {
            @unlink($localImage);
        }

        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    #[Test]
    public function non_strict_base64_is_rejected(): void
    {
        $user = User::factory()->create();

        // Characters outside the base64 alphabet were silently skipped by the
        // non-strict decoder.
        $this->actingAs($user)->postJson('/api/collections', [
            'name' => 'Sneaky',
            'photo_data' => 'data:image/png;base64,!!'.base64_encode(self::pngBytes()),
        ])->assertStatus(422);

        $this->actingAs($user)->postJson('/api/collections', [
            'name' => 'Empty',
            'photo_data' => 'data:image/png;base64,',
        ])->assertStatus(422);
    }

    #[Test]
    public function collection_photo_accepts_a_real_png(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/collections', [
            'name' => 'Legit',
            'photo_data' => 'data:image/png;base64,'.base64_encode(self::pngBytes()),
        ])->assertStatus(201);

        $files = Storage::disk('media')->allFiles('collections');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.webp', $files[0]);
    }

    #[Test]
    public function hut_image_accepts_a_real_png(): void
    {
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();

        $this->actingAs($user)->postJson('/api/huts', [
            'name' => 'Legit Hut',
            'club_id' => $club->id,
            'image' => ['data' => 'data:image/png;base64,'.base64_encode(self::pngBytes())],
        ])->assertStatus(201);

        $hut = Hut::where('name', 'Legit Hut')->firstOrFail();
        $this->assertStringStartsWith('huts/', $hut->image);
        Storage::disk('media')->assertExists($hut->image);
    }

    #[Test]
    public function uploaded_hut_file_is_checked_by_content_not_name(): void
    {
        $user = User::factory()->admin()->create();
        $club = Club::factory()->create();

        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        $this->actingAs($user)->post('/api/huts', [
            'name' => 'Sneaky Upload',
            'club_id' => $club->id,
            'image' => ['data' => new UploadedFile($path, 'photo.png', 'image/png', null, true)],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame([], Storage::disk('media')->allFiles());
    }
}
