<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\Tools\ParseLogbookCsvTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParseLogbookCsvToolTest extends TestCase
{
    use RefreshDatabase;

    private ParseLogbookCsvTool $tool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tool = new ParseLogbookCsvTool();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    #[Test]
    public function empty_content_returns_error(): void
    {
        $result = $this->tool->handle(['csv_content' => ''], $this->user);

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['trips']);
    }

    #[Test]
    public function whitespace_only_content_returns_error(): void
    {
        $result = $this->tool->handle(['csv_content' => "   \n  \t  "], $this->user);

        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function header_only_returns_error(): void
    {
        $result = $this->tool->handle(['csv_content' => "date,cave,description\n"], $this->user);

        $this->assertArrayHasKey('error', $result);
    }

    // -------------------------------------------------------------------------
    // Delimiter detection
    // -------------------------------------------------------------------------

    #[Test]
    public function detects_comma_delimiter(): void
    {
        $csv = "date,cave,description\n2024-06-01,Gaping Gill,Great trip";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertSame('comma', $result['detected_delimiter']);
        $this->assertCount(1, $result['trips']);
    }

    #[Test]
    public function detects_tab_delimiter(): void
    {
        $csv = "date\tcave\tdescription\n2024-06-01\tGaping Gill\tGreat trip";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertSame('tab', $result['detected_delimiter']);
        $this->assertCount(1, $result['trips']);
    }

    // -------------------------------------------------------------------------
    // Column header mapping
    // -------------------------------------------------------------------------

    #[Test]
    public function maps_cave_column_variants(): void
    {
        foreach (['cave', 'system', 'location', 'place', 'site'] as $header) {
            $csv = "date,{$header}\n2024-06-01,Ogof Ffynnon Ddu";
            $result = $this->tool->handle(['csv_content' => $csv], $this->user);
            $this->assertCount(1, $result['trips'], "Expected 1 trip for header '{$header}'");
            $this->assertSame('Ogof Ffynnon Ddu', $result['trips'][0]['cave_name_raw']);
        }
    }

    #[Test]
    public function maps_duration_column_variants(): void
    {
        $csv = "date,cave,hours\n2024-06-01,Gaping Gill,2";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertCount(1, $result['trips']);
        $this->assertSame(120, $result['trips'][0]['duration_minutes']);
    }

    #[Test]
    public function maps_companions_column_variants(): void
    {
        foreach (['companions', 'people', 'participants', 'team', 'party'] as $header) {
            $csv = "date,cave,{$header}\n2024-06-01,Gaping Gill,Alice and Bob";
            $result = $this->tool->handle(['csv_content' => $csv], $this->user);
            $this->assertCount(1, $result['trips'], "Expected trip for header '{$header}'");
            $this->assertSame('Alice and Bob', $result['trips'][0]['companions_raw']);
        }
    }

    // -------------------------------------------------------------------------
    // Date parsing
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('dateFormatProvider')]
    public function parses_date_formats(string $raw, string $expected): void
    {
        $csv = "date,cave\n{$raw},Gaping Gill";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertCount(1, $result['trips']);
        $this->assertSame($expected, $result['trips'][0]['date']);
        $this->assertSame('high', $result['trips'][0]['date_confidence']);
    }

    public static function dateFormatProvider(): array
    {
        return [
            'ISO format' => ['2024-06-15', '2024-06-15'],
            'DD/MM/YYYY slash' => ['15/06/2024', '2024-06-15'],
            'DD-MM-YYYY dash' => ['15-06-2024', '2024-06-15'],
            'DD.MM.YYYY dot' => ['15.06.2024', '2024-06-15'],
            'YYYY/MM/DD slash' => ['2024/06/15', '2024-06-15'],
            'DD/MM/YY two-digit' => ['15/06/24', '2024-06-15'],
            'DD/MM/YY century' => ['15/06/98', '1998-06-15'],
        ];
    }

    #[Test]
    public function unparseable_date_gives_low_confidence(): void
    {
        $csv = "date,cave\nnot-a-date,Gaping Gill";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $trip = $result['trips'][0] ?? null;
        if ($trip !== null) {
            $this->assertSame('low', $trip['date_confidence']);
        }
    }

    // -------------------------------------------------------------------------
    // Duration parsing
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('durationProvider')]
    public function parses_duration_formats(string $raw, int $expectedMinutes): void
    {
        $csv = "date,cave,duration\n2024-06-01,Gaping Gill,{$raw}";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertCount(1, $result['trips']);
        $this->assertSame($expectedMinutes, $result['trips'][0]['duration_minutes']);
    }

    public static function durationProvider(): array
    {
        return [
            'decimal hours' => ['2.5', 150],
            'HH:MM' => ['2:30', 150],
            'raw minutes' => ['150', 150],
            'integer hours' => ['3', 180],
            '2h 30m' => ['2h 30m', 150],
            '2h30m no space' => ['2h30m', 150],
        ];
    }

    // -------------------------------------------------------------------------
    // Missing field handling
    // -------------------------------------------------------------------------

    #[Test]
    public function row_with_neither_cave_nor_date_is_skipped(): void
    {
        $csv = "date,cave,description\n,,Just a note with no useful info";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertSame(0, $result['total_rows_parsed']);
        $this->assertSame(1, $result['rows_skipped']);
    }

    #[Test]
    public function row_with_cave_but_no_date_is_included(): void
    {
        $csv = "date,cave,description\n,Gaping Gill,Great trip";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertCount(1, $result['trips']);
        $this->assertNull($result['trips'][0]['date']);
        $this->assertSame('low', $result['trips'][0]['date_confidence']);
    }

    #[Test]
    public function missing_duration_results_in_null_minutes(): void
    {
        $csv = "date,cave\n2024-06-01,Gaping Gill";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertCount(1, $result['trips']);
        $this->assertNull($result['trips'][0]['duration_minutes']);
    }

    // -------------------------------------------------------------------------
    // Multiple rows and metadata
    // -------------------------------------------------------------------------

    #[Test]
    public function parses_multiple_rows(): void
    {
        $csv = implode("\n", [
            'date,cave,description',
            '2024-01-10,Gaping Gill,Trip 1',
            '2024-02-20,Ogof Ffynnon Ddu,Trip 2',
            '2024-03-15,Lancaster Hole,Trip 3',
        ]);
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertSame(3, $result['total_rows_parsed']);
        $this->assertSame(0, $result['rows_skipped']);
        $this->assertFalse($result['truncated']);
    }

    #[Test]
    public function row_numbers_are_one_indexed_from_data(): void
    {
        $csv = implode("\n", [
            'date,cave',
            '2024-01-10,Gaping Gill',
            '2024-02-20,OFD',
        ]);
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertSame(2, $result['trips'][0]['row']);
        $this->assertSame(3, $result['trips'][1]['row']);
    }

    #[Test]
    public function auto_generates_trip_name_when_absent(): void
    {
        $csv = "date,cave\n2024-06-01,Gaping Gill";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $trip = $result['trips'][0];
        $this->assertNotEmpty($trip['trip_name']);
        $this->assertStringContainsString('Gaping Gill', $trip['trip_name']);
    }

    #[Test]
    public function uses_provided_trip_name_over_auto_generated(): void
    {
        $csv = "date,cave,name\n2024-06-01,Gaping Gill,My Custom Title";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertSame('My Custom Title', $result['trips'][0]['trip_name']);
    }

    #[Test]
    public function crlf_line_endings_handled_correctly(): void
    {
        $csv = "date,cave,description\r\n2024-06-01,Gaping Gill,CRLF test";
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertCount(1, $result['trips']);
        $this->assertSame('Gaping Gill', $result['trips'][0]['cave_name_raw']);
    }

    #[Test]
    public function quoted_fields_with_commas_are_handled(): void
    {
        $csv = 'date,cave,description'."\n".'2024-06-01,"Ogof Ffynnon Ddu, South Wales","Great trip, rigged the main pitch"';
        $result = $this->tool->handle(['csv_content' => $csv], $this->user);

        $this->assertCount(1, $result['trips']);
        $this->assertSame('Ogof Ffynnon Ddu, South Wales', $result['trips'][0]['cave_name_raw']);
    }
}
