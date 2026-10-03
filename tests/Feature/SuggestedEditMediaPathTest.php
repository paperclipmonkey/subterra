<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Club;
use App\Models\Collection;
use App\Models\SuggestedEdit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Suggested-edit data is client-controlled. Media paths in it must be ones the
 * pending-media pipeline produced, or an admin approving an innocent-looking
 * suggestion would move or republish an arbitrary file on the media disk.
 */
class SuggestedEditMediaPathTest extends TestCase
{
    use RefreshDatabase;

    private const VICTIM = 'avatars/victim.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Mail::fake();
        Storage::disk('media')->put(self::VICTIM, 'victim bytes');

        if (!extension_loaded('imagick')) {
            config(['image.driver' => \Intervention\Image\Drivers\Gd\Driver::class]);
        }
    }

    private function approvedMember(): User
    {
        $user = User::factory()->withApprovedClub()->create();
        $user->clubs()->attach(Club::factory()->create(), ['status' => 'approved']);

        return $user;
    }

    private static function pngDataUri(): string
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    public static function foreignPaths(): array
    {
        return [
            'other user file' => [self::VICTIM],
            'traversal from pending' => ['pending_edits/../avatars/victim.jpg'],
            'traversal from target dir' => ['cave_systems/../avatars/victim.jpg'],
            'absolute' => ['/avatars/victim.jpg'],
        ];
    }

    #[Test]
    #[DataProvider('foreignPaths')]
    public function submitting_a_foreign_media_path_drops_it(string $path): void
    {
        $system = CaveSystem::factory()->create();

        $this->actingAs($this->approvedMember())->postJson('/api/suggested-edits', [
            'suggestable_type' => 'cave_system',
            'suggestable_id' => $system->id,
            'suggested_data' => [
                'name' => 'Renamed',
                'media' => [['data' => $path, 'name' => 'x.jpg', 'mime_type' => 'image/jpeg']],
            ],
        ])->assertStatus(201);

        $suggestion = SuggestedEdit::latest('id')->firstOrFail();
        $this->assertSame([], $suggestion->suggested_data['media']);
    }

    #[Test]
    public function submitting_a_client_supplied_pending_path_drops_it(): void
    {
        // Another suggestion's pending upload must not be claimable.
        Storage::disk('media')->put('pending_edits/cave_system/someone_else_media.webp', 'x');
        $system = CaveSystem::factory()->create();

        $this->actingAs($this->approvedMember())->postJson('/api/suggested-edits', [
            'suggestable_type' => 'cave_system',
            'suggestable_id' => $system->id,
            'suggested_data' => [
                'media' => [['data' => 'pending_edits/cave_system/someone_else_media.webp']],
            ],
        ])->assertStatus(201);

        $this->assertSame([], SuggestedEdit::latest('id')->firstOrFail()->suggested_data['media']);
    }

    #[Test]
    #[DataProvider('foreignPaths')]
    public function approving_a_cave_system_suggestion_with_a_foreign_path_does_not_move_or_publish_it(string $path): void
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();

        // Written straight to the table, as a pre-fix row (or anything bypassing
        // submission) would be.
        $suggestion = SuggestedEdit::create([
            'user_id' => $this->approvedMember()->id,
            'suggestable_type' => CaveSystem::class,
            'suggestable_id' => $system->id,
            'suggested_data' => [
                'media' => [['data' => $path, 'name' => 'victim.jpg', 'mime_type' => 'image/jpeg', 'size' => 12]],
            ],
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve")->assertOk();

        Storage::disk('media')->assertExists(self::VICTIM);
        Storage::disk('media')->assertMissing("cave_system_files/{$system->id}/victim.jpg");
        $this->assertSame(0, $system->files()->count());
    }

    #[Test]
    public function approving_a_cave_suggestion_cannot_point_its_images_at_a_foreign_file(): void
    {
        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create();
        $cave->media()->create(['type' => 'hero', 'filename' => 'caves/original-hero.webp', 'title' => 'Old title']);

        $suggestion = SuggestedEdit::create([
            'user_id' => $this->approvedMember()->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'suggested_data' => [
                'hero_image' => ['data' => null, 'filename' => self::VICTIM, 'title' => 'New title'],
                'entrance_image' => self::VICTIM,
            ],
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve")->assertOk();

        $cave->refresh();
        // The metadata edit applies; the file reference does not change.
        $this->assertSame('caves/original-hero.webp', $cave->heroImage->filename);
        $this->assertSame('New title', $cave->heroImage->title);
        $this->assertNull($cave->entranceImage);
        Storage::disk('media')->assertExists(self::VICTIM);
    }

    #[Test]
    public function approving_a_collection_suggestion_cannot_point_its_photo_at_a_foreign_file(): void
    {
        $admin = User::factory()->admin()->create();
        $collection = Collection::factory()->create(['photo_path' => 'collections/original.webp']);

        $suggestion = SuggestedEdit::create([
            'user_id' => $this->approvedMember()->id,
            'suggestable_type' => Collection::class,
            'suggestable_id' => $collection->id,
            'suggested_data' => ['name' => 'Renamed', 'photo_path' => self::VICTIM],
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve")->assertOk();

        $collection->refresh();
        $this->assertSame('Renamed', $collection->name);
        $this->assertSame('collections/original.webp', $collection->photo_path);
        Storage::disk('media')->assertExists(self::VICTIM);
    }

    #[Test]
    public function a_genuine_cave_system_upload_is_published_with_a_server_derived_mime_type(): void
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();

        $this->actingAs($this->approvedMember())->postJson('/api/suggested-edits', [
            'suggestable_type' => 'cave_system',
            'suggestable_id' => $system->id,
            'suggested_data' => [
                'media' => [['data' => self::pngDataUri(), 'name' => 'survey.png', 'mime_type' => 'text/html', 'size' => 999999]],
            ],
        ])->assertStatus(201);

        $suggestion = SuggestedEdit::latest('id')->firstOrFail();
        $pendingPath = $suggestion->suggested_data['media'][0]['data'];
        $this->assertStringStartsWith('pending_edits/cave_system/', $pendingPath);

        $this->actingAs($admin)->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve")->assertOk();

        $file = $system->files()->firstOrFail();
        Storage::disk('media')->assertExists("cave_system_files/{$system->id}/{$file->filename}");
        Storage::disk('media')->assertMissing($pendingPath);
        $this->assertSame('image/webp', $file->mime_type);
        $this->assertSame('survey.png', $file->original_filename);
        $this->assertSame(Storage::disk('media')->size("cave_system_files/{$system->id}/{$file->filename}"), $file->size);
    }

    #[Test]
    public function submitting_a_non_image_as_base64_media_is_rejected(): void
    {
        $system = CaveSystem::factory()->create();

        $this->actingAs($this->approvedMember())->postJson('/api/suggested-edits', [
            'suggestable_type' => 'cave_system',
            'suggestable_id' => $system->id,
            'suggested_data' => [
                'media' => [['data' => 'data:image/png;base64,'.base64_encode("%PDF-1.4\n%%EOF\n")]],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, SuggestedEdit::count());
    }
}
