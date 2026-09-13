<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\MedalAwardedMail;
use App\Models\Medal;
use App\Models\User;
use Database\Seeders\MedalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MedalAwardedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_seeded_medal_ships_with_the_raster_the_email_needs(): void
    {
        $this->seed(MedalSeeder::class);

        $disk = Storage::disk('medals');
        $missing = [];

        foreach (Medal::all() as $medal) {
            if (!$medal->image_path || str_starts_with($medal->image_path, 'http')) {
                continue;
            }

            // Mail clients overwhelmingly do not render SVG, so the email uses a
            // PNG alongside each badge. The archivist medal shipped without one,
            // which is why its email arrived with no picture.
            $raster = preg_replace('/\.svg$/i', '.png', $medal->image_path);

            if (!$disk->exists($raster)) {
                $missing[] = $raster;
            }
        }

        $this->assertSame([], $missing, 'Medals missing a PNG for the awarded email: '.implode(', ', $missing));
    }

    #[Test]
    public function the_award_email_embeds_the_medal_image(): void
    {
        $this->seed(MedalSeeder::class);

        $user = User::factory()->create();
        $medal = Medal::where('image_path', 'archivist.svg')->firstOrFail();

        $rendered = (new MedalAwardedMail($user, $medal))->build()->render();

        $this->assertStringContainsString('archivist.png', $rendered);
        $this->assertStringNotContainsString('archivist.svg', $rendered);
    }

    #[Test]
    public function a_medal_with_no_raster_omits_the_image_rather_than_linking_a_missing_file(): void
    {
        $medal = Medal::create([
            'name' => 'Ghost Badge',
            'description' => 'Has artwork that was never rasterised.',
            'image_path' => 'not-rasterised-yet.svg',
        ]);

        $this->assertNull($medal->rasterImageUrl());

        $rendered = (new MedalAwardedMail(User::factory()->create(), $medal))->build()->render();

        $this->assertStringNotContainsString('not-rasterised-yet', $rendered);
        $this->assertStringContainsString('Ghost Badge', $rendered);
    }
}
