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
}
