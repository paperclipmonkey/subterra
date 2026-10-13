<?php

declare(strict_types=1);

namespace App\Services\TripImport;

use Illuminate\Support\Carbon;

/**
 * Turns a caving logbook export (CSV/TSV, as saved from Excel, Google Sheets,
 * Numbers or a hand-kept text file) into normalised trip rows.
 *
 * This is deliberately deterministic: everything a parser can work out is
 * worked out here, so the assistant only has to deal with the genuinely
 * ambiguous leftovers, and a small, cheap model is enough.
 */
class LogbookParser
{
    public const MAX_ROWS = 500;

    private const MAX_COMPANIONS = 30;

    /**
     * Header patterns per field. Exact matches win over substring matches,
     * and fields earlier in this list claim a column first in the substring
     * pass, so "cave entrance" becomes the entrance rather than the cave.
     *
     * @var array<string, string[]>
     */
    private const HEADER_PATTERNS = [
        'entrance_name' => ['entrance', 'entrance cave', 'cave entrance', 'entry', 'way in', 'in via'],
        'exit_name' => ['exit', 'exit cave', 'cave exit', 'way out', 'out via'],
        'start_time' => ['start time', 'time in', 'entry time', 'start', 'time underground started'],
        'duration' => ['duration', 'time underground', 'underground time', 'tu', 'hours', 'hrs', 'mins', 'minutes', 'duration (hrs)', 'duration (mins)', 'length of trip'],
        'trip_name' => ['trip name', 'trip title', 'title'],
        'companions' => ['companions', 'participants', 'people', 'team', 'party', 'cavers', 'with', 'members', 'who'],
        'description' => ['description', 'notes', 'trip report', 'report', 'comments', 'details', 'remarks', 'narrative', 'log', 'summary'],
        'date' => ['date', 'trip date', 'visit date', 'day', 'when'],
        'cave_name' => ['cave', 'cave name', 'cave system', 'system', 'location', 'site', 'place', 'name'],
    ];

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     rows_skipped: int,
     *     truncated: bool,
     *     columns: array<string, string>,
     *     unused_columns: string[],
     *     date_format: string,
     *     delimiter: string,
     * }
     *
     * @throws InvalidLogbookException
     */
    public function parse(string $content): array
    {
        // Excel's "CSV UTF-8" starts with a byte-order mark, which would
        // otherwise stick to the first header and stop it matching.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if (!mb_check_encoding($content, 'UTF-8')) {
            // Older Excel saves as Windows-1252; names like "Ogof Ffynnon Ddu"
            // survive either way, but accented and curly characters would not.
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        if (trim($content) === '') {
            throw new InvalidLogbookException('The file is empty.');
        }

        $delimiter = $this->detectDelimiter($content);
        $records = $this->readRecords($content, $delimiter);

        // Skip leading blank lines and title rows ("My caving log 2019") until
        // something that looks like a header.
        $headerIndex = null;
        $columnMap = [];
        foreach (array_slice($records, 0, 10, true) as $i => $record) {
            $map = $this->buildColumnMap($record);
            if (isset($map['cave_name']) || (isset($map['date']) && count($map) >= 2)) {
                $headerIndex = $i;
                $columnMap = $map;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new InvalidLogbookException(
                'I couldn\'t find a header row. The first row of the file should name the columns, '
                .'with at least a "Cave" and a "Date" column (others such as "Entrance", "Duration", '
                .'"With" and "Notes" are optional).'
            );
        }

        if (!isset($columnMap['cave_name']) && !isset($columnMap['entrance_name'])) {
            throw new InvalidLogbookException('I couldn\'t find a column naming the cave. Please add a "Cave" column header.');
        }

        $headers = array_map(fn ($h) => trim((string) $h), $records[$headerIndex]);
        $durationUnit = $this->durationUnitFromHeader($headers[$columnMap['duration'] ?? -1] ?? '');

        $dataRecords = array_slice($records, $headerIndex + 1, null, true);
        $dateValues = [];
        $rawRows = [];
        foreach ($dataRecords as $i => $record) {
            if (count(array_filter($record, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = ['source_line' => $i + 1];
            foreach ($columnMap as $field => $col) {
                $row[$field] = trim((string) ($record[$col] ?? ''));
            }
            $rawRows[] = $row;
            if (($row['date'] ?? '') !== '') {
                $dateValues[] = $row['date'];
            }
        }

        $truncated = count($rawRows) > self::MAX_ROWS;
        $rawRows = array_slice($rawRows, 0, self::MAX_ROWS);
        $dateOrder = $this->inferDateOrder($dateValues);

        $rows = [];
        $skipped = 0;
        foreach ($rawRows as $raw) {
            $cave = $raw['cave_name'] ?? '';
            $entrance = $raw['entrance_name'] ?? '';
            if ($cave === '' && $entrance === '' && ($raw['date'] ?? '') === '') {
                ++$skipped;

                continue;
            }

            $rows[] = [
                'source_line' => $raw['source_line'],
                'cave_name' => $this->clip($cave),
                'entrance_name' => $this->clip($entrance),
                'exit_name' => $this->clip($raw['exit_name'] ?? ''),
                'date' => self::parseDate($raw['date'] ?? '', $dateOrder),
                'date_raw' => $this->clip($raw['date'] ?? ''),
                'start_time' => self::parseTime($raw['start_time'] ?? ''),
                'duration_minutes' => self::parseDuration($raw['duration'] ?? '', $durationUnit),
                'name' => $this->clip($raw['trip_name'] ?? ''),
                'description' => mb_substr($raw['description'] ?? '', 0, 10000),
                'companions' => self::splitCompanions($raw['companions'] ?? ''),
            ];
        }

        $usedColumns = array_flip($columnMap);

        return [
            'rows' => $rows,
            'rows_skipped' => $skipped,
            'truncated' => $truncated,
            'columns' => collect($columnMap)->map(fn ($col) => $headers[$col] ?? '')->all(),
            'unused_columns' => array_values(array_filter(
                $headers,
                fn ($h, $col) => $h !== '' && !isset($usedColumns[$col]),
                ARRAY_FILTER_USE_BOTH
            )),
            'date_format' => $dateOrder === 'mdy' ? 'MM/DD/YYYY (US)' : 'DD/MM/YYYY (UK)',
            'delimiter' => match ($delimiter) {
                "\t" => 'tab',
                ';' => 'semicolon',
                default => 'comma',
            },
        ];
    }

    /**
     * Parse one date. Numeric dates follow $order ('dmy' — the UK default — or
     * 'mdy'); textual dates ("3rd June 2021", "Sat 3 Jun 21") and Excel serial
     * numbers are also understood. Impossible dates (31/02) return null.
     */
    public static function parseDate(string $raw, string $order = 'dmy'): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // ISO, optionally with a time part (Google Sheets exports)
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/', $raw, $m)) {
            return self::validDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // YYYY/MM/DD
        if (preg_match('#^(\d{4})[/.](\d{1,2})[/.](\d{1,2})$#', $raw, $m)) {
            return self::validDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // DD/MM/YYYY or MM/DD/YYYY, 2- or 4-digit year, optional trailing time
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2}|\d{4})(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$#', $raw, $m)) {
            $year = (int) $m[3];
            if (strlen($m[3]) === 2) {
                // Two-digit years: anything not in the future is this century.
                $year += ($year <= (int) now()->format('y')) ? 2000 : 1900;
            }
            [$day, $month] = $order === 'mdy' ? [(int) $m[2], (int) $m[1]] : [(int) $m[1], (int) $m[2]];

            return self::validDate($year, $month, $day);
        }

        // Excel serial date number (days since 1899-12-30), e.g. 44562
        if (preg_match('/^\d{5}(?:\.\d+)?$/', $raw) && (int) $raw > 10000 && (int) $raw < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $raw)->format('Y-m-d');
        }

        // Textual: strip weekday names and ordinal suffixes, then let Carbon try
        $clean = preg_replace('/\b(mon|tue|tues|wed|thu|thur|thurs|fri|sat|sun)[a-z]*\.?,?\s*/i', '', $raw) ?? $raw;
        $clean = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $clean) ?? $clean;
        $clean = trim(str_replace(',', ' ', $clean));
        if (!preg_match('/[a-z]{3}/i', $clean)) {
            return null;
        }

        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        // A trailing two-digit year must use `y`, or "3 Jun 21" parses as AD 21.
        $formats = preg_match('/\b\d{2}$/', $clean)
            ? ['j M y', 'j F y', 'M j y', 'F j y']
            : ['j M Y', 'j F Y', 'M j Y', 'F j Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $clean);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** Parse an entry time ("10:30", "1030", "10.30am", "2pm") to HH:MM. */
    public static function parseTime(string $raw): ?string
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return null;
        }

        if (!preg_match('/^(\d{1,2})(?:[:.]?(\d{2}))?\s*(am|pm)?$/', $raw, $m)) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $meridiem = $m[3] ?? null;
        if ($meridiem === null && !isset($m[2])) {
            // A bare number ("10") is too ambiguous to be a time.
            return null;
        }
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        return ($hour < 24 && $minute < 60) ? sprintf('%02d:%02d', $hour, $minute) : null;
    }

    /**
     * Convert a duration to whole minutes. Handles "2:30", "2h 30m", "2h30",
     * "2.5 hrs", "90 mins", "2½". A bare number uses the column's unit when its
     * header names one ("Hours", "TU (mins)"), else ≤ 24 means hours.
     */
    public static function parseDuration(string $raw, ?string $unit = null): ?int
    {
        $raw = strtolower(trim(str_replace(['½', '¼', '¾'], ['.5', '.25', '.75'], $raw)));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d+):(\d{2})(?::\d{2})?$/', $raw, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*h(?:ou)?r?s?\s*(\d+)\s*m?(?:in(?:ute)?s?)?$/', $raw, $m)) {
            return (int) round((float) $m[1] * 60) + (int) $m[2];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*h(?:ou)?r?s?$/', $raw, $m)) {
            return (int) round((float) $m[1] * 60);
        }

        if (preg_match('/^(\d+)\s*m(?:in(?:ute)?s?)?$/', $raw, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            $value = (float) $raw;
            if ($unit === 'minutes') {
                return (int) round($value);
            }
            if ($unit === 'hours' || $value <= 24) {
                return (int) round($value * 60);
            }

            return (int) round($value);
        }

        return null;
    }

    /**
     * Split a companions cell ("Alice Smith, Bob & Carol Jones and Dave") into
     * individual names.
     *
     * @return string[]
     */
    public static function splitCompanions(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*(?:[,;\/&+\n|]|\band\b|\bplus\b)\s*/iu', $raw) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $name = trim($part, " \t.-()");
            if ($name === '' || mb_strlen($name) > 100) {
                continue;
            }
            $names[mb_strtolower($name)] ??= $name;
        }

        return array_slice(array_values($names), 0, self::MAX_COMPANIONS);
    }

    private static function validDate(int $year, int $month, int $day): ?string
    {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    /**
     * Work out whether numeric dates in this file are day-first or month-first:
     * a single "25/12/2020" proves day-first, "12/25/2020" month-first. With no
     * evidence either way we assume the UK convention.
     *
     * @param  string[]  $values
     */
    private function inferDateOrder(array $values): string
    {
        $dayFirst = 0;
        $monthFirst = 0;
        foreach ($values as $value) {
            if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.]\d{2,4}#', trim($value), $m)) {
                if ((int) $m[1] > 12) {
                    ++$dayFirst;
                } elseif ((int) $m[2] > 12) {
                    ++$monthFirst;
                }
            }
        }

        return $monthFirst > $dayFirst ? 'mdy' : 'dmy';
    }

    private function durationUnitFromHeader(string $header): ?string
    {
        $header = strtolower($header);
        if (preg_match('/\b(min|mins|minutes)\b/', $header)) {
            return 'minutes';
        }
        if (preg_match('/\b(h|hr|hrs|hour|hours)\b/', $header)) {
            return 'hours';
        }

        return null;
    }

    private function detectDelimiter(string $content): string
    {
        $firstLines = implode("\n", array_slice(explode("\n", $content), 0, 5));
        // Ignore delimiters inside quoted cells
        $unquoted = preg_replace('/"[^"]*"/', '', $firstLines) ?? $firstLines;
        $counts = [
            ',' => substr_count($unquoted, ','),
            "\t" => substr_count($unquoted, "\t"),
            ';' => substr_count($unquoted, ';'),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * Read every record with fgetcsv so quoted cells may span lines — long
     * trip notes typed with Alt+Enter in Excel do exactly that.
     *
     * @return array<int, array<int, string|null>>
     */
    private function readRecords(string $content, string $delimiter): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $records = [];
        // Generous cap: blank lines are dropped later, so the file can be a
        // little longer than MAX_ROWS and still be read in full.
        while (($record = fgetcsv($stream, null, $delimiter, '"', '')) !== false && count($records) < self::MAX_ROWS * 2) {
            $records[] = $record;
        }
        fclose($stream);

        return $records;
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<string, int>
     */
    private function buildColumnMap(array $headers): array
    {
        $normalised = array_map(
            fn ($h) => trim(preg_replace('/\s+/', ' ', strtolower(trim((string) $h, " \t*:"))) ?? ''),
            $headers
        );

        $map = [];
        $used = [];

        // Pass 1: exact header matches.
        foreach (self::HEADER_PATTERNS as $field => $candidates) {
            foreach ($normalised as $idx => $header) {
                if (!isset($used[$idx]) && in_array($header, $candidates, true)) {
                    $map[$field] = $idx;
                    $used[$idx] = true;
                    break;
                }
            }
        }

        // Pass 2: whole-word substring matches for fields still unmapped.
        foreach (self::HEADER_PATTERNS as $field => $candidates) {
            if (isset($map[$field])) {
                continue;
            }
            foreach ($candidates as $candidate) {
                if (mb_strlen($candidate) < 3) {
                    continue;
                }
                foreach ($normalised as $idx => $header) {
                    if (!isset($used[$idx]) && $header !== '' && preg_match('/\b'.preg_quote($candidate, '/').'\b/', $header)) {
                        $map[$field] = $idx;
                        $used[$idx] = true;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    private function clip(string $value): string
    {
        return mb_substr($value, 0, 255);
    }
}
