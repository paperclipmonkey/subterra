<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Support\CaveName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-registry name collisions: "Giant's Cave" exists in both Mendip and the
 * Peak District. A sync must only adopt a same-named record when it is plausibly
 * the same physical place (same registry, unowned, or geographically close).
 */
class CaveNameRegistryMatchingTest extends TestCase
{
    use RefreshDatabase;

    // Mendip vs Castleton — same name, ~230 km apart.
    private const MENDIP = ['lat' => 51.27, 'lng' => -2.66];

    private const PEAK = ['lat' => 53.34, 'lng' => -1.78];

    private function giantsCave(array $attrs = []): Cave
    {
        return Cave::factory()->create(array_merge([
            'name' => "Giant's Cave",
            'location_lat' => self::MENDIP['lat'],
            'location_lng' => self::MENDIP['lng'],
            'registry' => 'mcra',
            'registry_id' => '1827',
        ], $attrs));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_adopt_a_same_named_cave_from_another_registry_far_away(): void
    {
        $mendip = $this->giantsCave();

        // The Peak District sync sees its own Giant's Cave, far from the Mendip one.
        $match = CaveName::findCaveForRegistry("Giant's Cave", 'peak_district_giants-cave', 'pdc', self::PEAK['lat'], self::PEAK['lng']);

        $this->assertNull($match, 'A different-region cave with the same name must not be adopted.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adopts_a_same_named_cave_from_another_registry_at_the_same_place(): void
    {
        $mendip = $this->giantsCave();

        // A second registry lists the *same* Mendip cave — coordinates coincide.
        $match = CaveName::findCaveForRegistry("Giant's Cave", 'x', 'gsg', self::MENDIP['lat'] + 0.005, self::MENDIP['lng'] - 0.005);

        $this->assertNotNull($match);
        $this->assertSame($mendip->id, $match->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adopts_an_unowned_cave_by_name_regardless_of_location(): void
    {
        $manual = $this->giantsCave(['registry' => null, 'registry_id' => null]);

        $match = CaveName::findCaveForRegistry("Giant's Cave", 'x', 'pdc', self::PEAK['lat'], self::PEAK['lng']);

        $this->assertNotNull($match);
        $this->assertSame($manual->id, $match->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adopts_its_own_registrys_cave_even_if_coordinates_drift(): void
    {
        $cave = $this->giantsCave(['registry' => 'pdc', 'registry_id' => 'giants-hole']);

        $match = CaveName::findCaveForRegistry("Giant's Cave", 'x', 'pdc', self::PEAK['lat'], self::PEAK['lng']);

        $this->assertNotNull($match);
        $this->assertSame($cave->id, $match->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_adopt_a_system_owned_by_another_registry_far_away(): void
    {
        $system = CaveSystem::create(['name' => "Giant's Cave", 'slug' => 'giants-cave', 'length' => 0, 'vertical_range' => 0]);
        $this->giantsCave(['cave_system_id' => $system->id]); // Mendip / mcra

        $match = CaveName::findSystemForRegistry("Giant's Cave", 'giants-cave', 'pdc', self::PEAK['lat'], self::PEAK['lng']);

        $this->assertNull($match, 'A different-region system with the same name/slug must not be adopted.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adopts_a_system_owned_by_another_registry_at_the_same_place(): void
    {
        $system = CaveSystem::create(['name' => "Giant's Cave", 'slug' => 'giants-cave', 'length' => 0, 'vertical_range' => 0]);
        $this->giantsCave(['cave_system_id' => $system->id]); // Mendip / mcra

        $match = CaveName::findSystemForRegistry("Giant's Cave", 'giants-cave', 'gsg', self::MENDIP['lat'], self::MENDIP['lng']);

        $this->assertNotNull($match);
        $this->assertSame($system->id, $match->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adopts_an_empty_system_by_name(): void
    {
        $system = CaveSystem::create(['name' => "Giant's Cave", 'slug' => 'giants-cave', 'length' => 0, 'vertical_range' => 0]);

        $match = CaveName::findSystemForRegistry("Giant's Cave", 'giants-cave', 'pdc', self::PEAK['lat'], self::PEAK['lng']);

        $this->assertNotNull($match);
        $this->assertSame($system->id, $match->id);
    }
}
