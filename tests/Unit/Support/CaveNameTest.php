<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CaveName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CaveNameTest extends TestCase
{
    #[Test]
    public function it_collapses_capitalisation_differences(): void
    {
        $this->assertSame(
            CaveName::normalise('Uamh an Claonaite'),
            CaveName::normalise('Uamh An Claonaite'),
        );
    }

    #[Test]
    public function it_ignores_apostrophes(): void
    {
        $this->assertSame(
            CaveName::normalise("St Cuthbert's Swallet"),
            CaveName::normalise('St Cuthberts Swallet'),
        );
    }

    #[Test]
    public function it_trims_surrounding_whitespace(): void
    {
        $this->assertSame('swildons hole', CaveName::normalise("  Swildon's Hole  "));
    }

    #[Test]
    public function it_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', CaveName::normalise(null));
        $this->assertSame('', CaveName::normalise('   '));
    }

    #[Test]
    public function it_keeps_distinct_names_distinct(): void
    {
        $this->assertNotSame(
            CaveName::normalise('Swildons Hole'),
            CaveName::normalise('Eastwater Cavern'),
        );
    }

    #[Test]
    public function curly_apostrophes_are_apostrophes(): void
    {
        $this->assertSame(CaveName::normalise("It's a Cracker"), CaveName::normalise('It’s a Cracker'));
    }

    /** @return list<array{string, string}> */
    public static function sameCaveSpeltDifferently(): array
    {
        return [
            ['Thrupe Lane Swallett', 'Thrupe Lane Swallet'],
            ['GB cave', 'G.B. Cave'],
            ['Vespers Pot', 'Vesper Pot'],
            ['Ogof Tarddiad Rhymni', 'Ogof Tarddiad Rhymney'],
            ['Pwll Y Rhyd Cave', 'Ogof Pwll-y-Rhyd'],
            ['Ogof Dwy Sir', 'Ogof Ddwysir'],
            ['Eye Holes', 'Eyeholes'],
            ['Poll Breugair', "Liar's Sink (Poll Breugair)"],
            ["Swildon's Hole Cavern", "Swildon's Hole"],
            ['Hagg Gill', 'Hagg Gill Pot'],
            ['Calf Holes / Dry Lathe Cave', 'Calf Holes'],
            ['Eglwys Faen Crawl Entrance', 'Eglwys Faen'],
            ['Entrance to Ravenswell', "Raven's Well"],
            ['Yordas Pot (Top Entrance)', 'Yordas Pot'],
            ['Uamh a’ Tartair', 'Tartair'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('sameCaveSpeltDifferently')]
    public function it_sees_the_same_cave_spelt_differently(string $a, string $b): void
    {
        $this->assertTrue(CaveName::similar($a, $b), "{$a} ~ {$b}");
        $this->assertTrue(CaveName::similar($b, $a), "{$b} ~ {$a}");
    }

    /** @return list<array{string, string}> */
    public static function differentCaves(): array
    {
        return [
            ['Cwm Dwr (OFD)', 'Cwm Dwr 2'],
            ['Rift Cave', "Reed's Cave"],
            ['Barton Cave', 'Barton Shelter'],
            ['Great Douk Cave', 'Little Douk Cave'],
            ['Blackmoor Swallet', "Stainsby's Shaft"],
            ['Ogof Cnwc', 'Ogof Craig a Ffynnon'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('differentCaves')]
    public function it_keeps_different_caves_apart(string $a, string $b): void
    {
        $this->assertFalse(CaveName::similar($a, $b), "{$a} !~ {$b}");
    }

    #[Test]
    public function it_knows_a_name_for_one_way_in(): void
    {
        foreach (['Yordas Pot (Top Entrance)', 'Sell Gill Holes (dry route)', 'Meregill Hole Aven Entrance', 'Lower Long Churn Cave'] as $name) {
            $this->assertTrue(CaveName::namesAnEntrance($name), $name);
        }
        foreach (['Yordas Pot', 'Storm Cave (Uamh na Doineann)', 'Notts 2 (Committee Pot)', 'Swildons Hole'] as $name) {
            $this->assertFalse(CaveName::namesAnEntrance($name), $name);
        }
    }
}
