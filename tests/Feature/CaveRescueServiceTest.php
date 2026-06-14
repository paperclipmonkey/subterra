<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\Incident;
use App\Models\Tag;
use App\Models\User;
use App\Services\CaveRescueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaveRescueServiceTest extends TestCase
{
    use RefreshDatabase;

    private function caveTaggedRegion(string $region): Cave
    {
        $cave = Cave::factory()->create();
        $tag = Tag::create(['tag' => $region, 'type' => 'cave', 'category' => 'region']);
        $cave->tags()->attach($tag->id);

        return $cave->fresh();
    }

    /** @return array<string, array{0:string,1:string,2:string,3:string}> */
    public static function regionProvider(): array
    {
        return [
            'Mendip' => ['Mendip', 'Avon and Somerset Police', 'Mendip Cave Rescue', 'MCR'],
            'Yorkshire' => ['Yorkshire', 'North Yorkshire Police', 'Cave Rescue Organisation', 'CRO'],
            'Peak District' => ['Peak District', 'Derbyshire Constabulary', 'Derbyshire Cave Rescue Organisation', 'DCRO'],
            'South Wales' => ['South Wales', 'Dyfed-Powys Police', 'South & Mid Wales Cave Rescue Team', 'SMWCRT'],
            'North Wales' => ['North Wales', 'North Wales Police', 'North Wales Cave Rescue Organisation', 'NWCRO'],
            'Devon' => ['Devon', 'Devon and Cornwall Police', 'Devon Cave Rescue Organisation', 'DevCRO'],
            'Forest of Dean' => ['Forest of Dean', 'Gloucestershire Constabulary', 'Gloucestershire Cave Rescue Group', 'GCRG'],
            'Scotland' => ['Scotland', 'Police Scotland', 'Scottish Cave Rescue Organisation', 'SCRO'],
            'Portland' => ['Portland', 'Dorset Police', 'Mendip Cave Rescue', 'MCR'],
        ];
    }

    #[DataProvider('regionProvider')]
    public function test_resolves_rescue_info_from_the_region_tag(string $region, string $police, string $team, string $abbr): void
    {
        $cave = $this->caveTaggedRegion($region);

        $info = app(CaveRescueService::class)->forCave($cave);

        $this->assertSame($region, $info['region']);
        $this->assertSame($police, $info['police_force']);
        $this->assertSame($team, $info['rescue_team']);
        $this->assertSame($abbr, $info['rescue_abbr']);
        $this->assertNotEmpty($info['procedure']);
    }

    public function test_every_seeded_region_resolves_to_a_real_police_force(): void
    {
        $this->seed(\Database\Seeders\TagSeeder::class);

        $regions = Tag::query()->where('category', 'region')->pluck('tag');

        $this->assertNotEmpty($regions, 'TagSeeder should seed at least one region tag.');

        $missing = [];

        foreach ($regions as $region) {
            $cave = $this->caveTaggedRegion($region);
            $info = app(CaveRescueService::class)->forCave($cave);

            $this->assertSame($region, $info['region'], "Region tag '{$region}' should resolve to itself.");

            // A seeded region with no configured police force falls back to the
            // generic default (police_force === null) — that's the bug this guards.
            if ($info['police_force'] === null) {
                $missing[] = $region;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These seeded region tags have no police force in config/cave_rescue.php: '.implode(', ', $missing)
        );
    }

    public function test_falls_back_when_cave_has_no_region_tag(): void
    {
        $cave = Cave::factory()->create();

        $info = app(CaveRescueService::class)->forCave($cave);

        $this->assertNull($info['region']);
        $this->assertNull($info['police_force']);
        $this->assertNotEmpty($info['rescue_team']); // generic fallback wording
    }

    public function test_falls_back_for_a_null_cave(): void
    {
        $info = app(CaveRescueService::class)->forCave(null);

        $this->assertNull($info['region']);
        $this->assertNull($info['police_force']);
    }

    public function test_incident_endpoint_includes_region_specific_rescue_info(): void
    {
        $admin = User::factory()->dutyOfficer()->create();
        $cave = $this->caveTaggedRegion('Peak District');
        $callout = Callout::factory()->create(['cave_id' => $cave->id, 'status' => 'triggered']);
        $incident = Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $this->actingAs($admin)->getJson("/api/admin/incidents/{$incident->id}")
            ->assertStatus(200)
            ->assertJsonPath('rescue_info.region', 'Peak District')
            ->assertJsonPath('rescue_info.police_force', 'Derbyshire Constabulary')
            ->assertJsonPath('rescue_info.rescue_team', 'Derbyshire Cave Rescue Organisation')
            ->assertJsonPath('rescue_info.rescue_abbr', 'DCRO');
    }
}
