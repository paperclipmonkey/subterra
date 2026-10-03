<?php

declare(strict_types=1);

namespace Tests\Unit\TripImport;

use App\Services\TripImport\InvalidLogbookException;
use App\Services\TripImport\LogbookParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogbookParserTest extends TestCase
{
    private LogbookParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LogbookParser();
    }

    #[Test]
    public function parses_a_simple_csv(): void
    {
        $result = $this->parser->parse("Date,Cave,Entrance,Duration,With,Notes\n14/06/2024,Gaping Gill,Bar Pot,4h30,Alice & Bob,Great trip");

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];
        $this->assertSame('2024-06-14', $row['date']);
        $this->assertSame('Gaping Gill', $row['cave_name']);
        $this->assertSame('Bar Pot', $row['entrance_name']);
        $this->assertSame(270, $row['duration_minutes']);
        $this->assertSame(['Alice', 'Bob'], $row['companions']);
        $this->assertSame('Great trip', $row['description']);
        $this->assertSame(2, $row['source_line']);
    }

    #[Test]
    public function strips_a_utf8_bom_from_the_first_header(): void
    {
        $result = $this->parser->parse("\xEF\xBB\xBFDate,Cave\n2024-06-14,OFD");

        $this->assertSame('2024-06-14', $result['rows'][0]['date']);
    }

    #[Test]
    public function detects_semicolon_and_tab_delimiters(): void
    {
        $this->assertSame('semicolon', $this->parser->parse("Date;Cave\n14/06/2024;OFD")['delimiter']);
        $this->assertSame('tab', $this->parser->parse("Date\tCave\n14/06/2024\tOFD")['delimiter']);
    }

    #[Test]
    public function keeps_multi_line_quoted_notes_in_one_row(): void
    {
        $result = $this->parser->parse("Date,Cave,Notes\n14/06/2024,OFD,\"Wet.\nVery wet.\"\n15/06/2024,Dan yr Ogof,Dry");

        $this->assertCount(2, $result['rows']);
        $this->assertSame("Wet.\nVery wet.", $result['rows'][0]['description']);
    }

    #[Test]
    public function skips_title_rows_above_the_header_and_blank_rows(): void
    {
        $result = $this->parser->parse("My caving log 2019\n\nDate,Cave\n14/06/2024,OFD\n,\n15/06/2024,GG");

        $this->assertCount(2, $result['rows']);
    }

    #[Test]
    public function infers_us_month_first_dates_from_the_file(): void
    {
        $result = $this->parser->parse("Date,Cave\n06/14/2024,OFD\n03/04/2024,GG");

        $this->assertSame('MM/DD/YYYY (US)', $result['date_format']);
        $this->assertSame('2024-06-14', $result['rows'][0]['date']);
        $this->assertSame('2024-03-04', $result['rows'][1]['date']);
    }

    #[Test]
    public function ambiguous_numeric_dates_default_to_uk_order(): void
    {
        $result = $this->parser->parse("Date,Cave\n03/04/2024,OFD");

        $this->assertSame('DD/MM/YYYY (UK)', $result['date_format']);
        $this->assertSame('2024-04-03', $result['rows'][0]['date']);
    }

    #[Test]
    public function uses_the_duration_unit_named_in_the_header(): void
    {
        $minutes = $this->parser->parse("Date,Cave,TU (mins)\n14/06/2024,OFD,5");
        $hours = $this->parser->parse("Date,Cave,Hours\n14/06/2024,OFD,30");

        $this->assertSame(5, $minutes['rows'][0]['duration_minutes']);
        $this->assertSame(1800, $hours['rows'][0]['duration_minutes']);
    }

    #[Test]
    public function maps_entrance_column_before_cave_for_cave_entrance_header(): void
    {
        $result = $this->parser->parse("Date,Cave System,Cave Entrance\n14/06/2024,Gaping Gill,Bar Pot");

        $this->assertSame('Gaping Gill', $result['rows'][0]['cave_name']);
        $this->assertSame('Bar Pot', $result['rows'][0]['entrance_name']);
    }

    #[Test]
    public function reports_columns_it_did_not_use(): void
    {
        $result = $this->parser->parse("Date,Cave,Weather\n14/06/2024,OFD,Sunny");

        $this->assertSame(['Weather'], $result['unused_columns']);
    }

    #[Test]
    public function rejects_a_file_with_no_recognisable_header(): void
    {
        $this->expectException(InvalidLogbookException::class);

        $this->parser->parse("foo,bar\n1,2");
    }

    #[Test]
    public function rejects_an_empty_file(): void
    {
        $this->expectException(InvalidLogbookException::class);

        $this->parser->parse("  \n ");
    }

    #[Test]
    public function truncates_very_long_files(): void
    {
        $lines = ['Date,Cave'];
        for ($i = 0; $i < LogbookParser::MAX_ROWS + 5; ++$i) {
            $lines[] = '14/06/2024,OFD';
        }

        $result = $this->parser->parse(implode("\n", $lines));

        $this->assertTrue($result['truncated']);
        $this->assertCount(LogbookParser::MAX_ROWS, $result['rows']);
    }

    #[Test]
    public function converts_windows_1252_text(): void
    {
        $result = $this->parser->parse("Date,Cave,Notes\n14/06/2024,OFD,".mb_convert_encoding('Café stop', 'Windows-1252', 'UTF-8'));

        $this->assertSame('Café stop', $result['rows'][0]['description']);
    }

    #[Test]
    #[DataProvider('dates')]
    public function parses_dates(string $raw, ?string $expected): void
    {
        $this->assertSame($expected, LogbookParser::parseDate($raw));
    }

    public static function dates(): array
    {
        return [
            'iso' => ['2021-06-03', '2021-06-03'],
            'iso with time' => ['2021-06-03T10:00:00', '2021-06-03'],
            'uk slashes' => ['03/06/2021', '2021-06-03'],
            'uk dots' => ['3.6.2021', '2021-06-03'],
            'two digit year' => ['03/06/21', '2021-06-03'],
            'two digit year last century' => ['03/06/95', '1995-06-03'],
            'text with ordinal' => ['3rd June 2021', '2021-06-03'],
            'text with weekday and short year' => ['Sat 3 Jun 21', '2021-06-03'],
            'us text' => ['June 3, 2021', '2021-06-03'],
            'excel serial' => ['44562', '2022-01-01'],
            'impossible date' => ['31/02/2021', null],
            'garbage' => ['sometime in spring', null],
            'empty' => ['', null],
        ];
    }

    #[Test]
    #[DataProvider('durations')]
    public function parses_durations(string $raw, ?int $expected): void
    {
        $this->assertSame($expected, LogbookParser::parseDuration($raw));
    }

    public static function durations(): array
    {
        return [
            'hh:mm' => ['2:30', 150],
            'h and m' => ['2h 30m', 150],
            'compact' => ['2h30', 150],
            'decimal hours' => ['2.5 hrs', 150],
            'minutes' => ['90 mins', 90],
            'bare small number is hours' => ['3', 180],
            'bare large number is minutes' => ['150', 150],
            'vulgar fraction' => ['2½', 150],
            'unreadable' => ['most of the day', null],
        ];
    }

    #[Test]
    public function parses_times(): void
    {
        $this->assertSame('10:30', LogbookParser::parseTime('10:30'));
        $this->assertSame('14:00', LogbookParser::parseTime('2pm'));
        $this->assertSame('09:15', LogbookParser::parseTime('9.15am'));
        $this->assertNull(LogbookParser::parseTime('10'));
        $this->assertNull(LogbookParser::parseTime('25:00'));
    }

    #[Test]
    public function splits_companion_lists(): void
    {
        $this->assertSame(
            ['Alice Smith', 'Bob', 'Carol Jones', 'Dave', 'Eve'],
            LogbookParser::splitCompanions('Alice Smith, Bob & Carol Jones and Dave; Eve; alice smith')
        );
        $this->assertSame([], LogbookParser::splitCompanions(''));
    }
}
