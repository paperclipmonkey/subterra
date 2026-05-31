<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\AssistantTool;

class ParseLogbookCsvTool implements AssistantTool
{
    // Maximum rows we'll attempt to parse in a single tool call
    private const MAX_ROWS = 500;

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'parse_logbook_csv',
                'description' => 'Parse raw CSV or TSV text from a caving logbook into structured trip data. '
                    .'The CSV may be messy: column names may vary, some fields may be missing, dates may use different formats. '
                    .'Returns an array of parsed trip entries ready for the user to review before creating. '
                    .'The user should paste their CSV/TSV data as a message. This tool normalises it.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'csv_content' => [
                            'type' => 'string',
                            'description' => 'The raw CSV or TSV content from the logbook. Include the header row.',
                        ],
                    ],
                    'required' => ['csv_content'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $rawContent = (string) ($arguments['csv_content'] ?? '');

        if (trim($rawContent) === '') {
            return ['error' => 'No CSV content provided.', 'trips' => []];
        }

        // Detect delimiter (tab vs comma)
        $lines = preg_split('/\r\n|\r|\n/', trim($rawContent)) ?: [];
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));

        if (count($lines) < 2) {
            return ['error' => 'CSV must have at least a header row and one data row.', 'trips' => []];
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',';

        // Parse header
        $headers = $this->parseCsvLine($firstLine, $delimiter);
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        // Map column indices to known field names
        $columnMap = $this->buildColumnMap($headers);

        // Parse data rows
        $dataLines = array_slice($lines, 1, self::MAX_ROWS);
        $trips = [];
        $skipped = 0;

        foreach ($dataLines as $lineIndex => $line) {
            $values = $this->parseCsvLine($line, $delimiter);

            // Pad values array to match header count
            while (count($values) < count($headers)) {
                $values[] = '';
            }

            $row = [];
            foreach ($columnMap as $field => $colIndex) {
                $row[$field] = trim($values[$colIndex] ?? '');
            }

            $parsed = $this->parseRow($row, $lineIndex + 2);
            if ($parsed === null) {
                ++$skipped;

                continue;
            }
            $trips[] = $parsed;
        }

        $truncated = count($dataLines) >= self::MAX_ROWS
            && count($lines) - 1 > self::MAX_ROWS;

        return [
            'total_rows_parsed' => count($trips),
            'rows_skipped' => $skipped,
            'truncated' => $truncated,
            'truncated_note' => $truncated
                ? 'Only the first '.self::MAX_ROWS.' rows were processed. Import in batches for larger logbooks.'
                : null,
            'detected_delimiter' => $delimiter === "\t" ? 'tab' : 'comma',
            'column_mapping' => array_keys($columnMap),
            'trips' => $trips,
        ];
    }

    /**
     * Build a map from semantic field name to column index, using fuzzy header matching.
     *
     * @param  string[]  $headers
     * @return array<string, int>
     */
    private function buildColumnMap(array $headers): array
    {
        // Priority-ordered candidate patterns for each field
        $patterns = [
            'cave_name' => ['cave', 'system', 'location', 'place', 'site', 'cave system', 'cave name'],
            'entrance_name' => ['entrance', 'pot', 'hole', 'shaft', 'entry', 'cave entrance'],
            'date' => ['date', 'trip date', 'visit date', 'start date', 'day'],
            'duration' => ['duration', 'time', 'hours', 'time underground', 'underground time', 'duration (hrs)', 'duration (min)', 'duration_minutes', 'mins', 'minutes', 'hr', 'hrs'],
            'description' => ['description', 'notes', 'report', 'trip report', 'comments', 'details', 'remarks', 'narrative', 'log'],
            'companions' => ['companions', 'people', 'participants', 'team', 'with', 'party', 'members', 'cavers'],
            'trip_name' => ['trip name', 'name', 'title', 'trip title'],
        ];

        $map = [];
        $usedIndices = [];

        foreach ($patterns as $field => $candidates) {
            foreach ($candidates as $candidate) {
                foreach ($headers as $idx => $header) {
                    if (isset($usedIndices[$idx])) {
                        continue;
                    }
                    if ($header === $candidate || str_contains($header, $candidate)) {
                        $map[$field] = $idx;
                        $usedIndices[$idx] = true;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Parse a single data row into a structured trip array, or null if the row
     * has insufficient data to create a trip.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>|null
     */
    private function parseRow(array $row, int $rowNumber): ?array
    {
        $caveName = $row['cave_name'] ?? '';
        $dateRaw = $row['date'] ?? '';

        // Skip rows that have neither cave name nor date — nothing useful
        if ($caveName === '' && $dateRaw === '') {
            return null;
        }

        // Parse date
        $parsedDate = null;
        $dateConfidence = 'low';
        if ($dateRaw !== '') {
            $parsedDate = $this->parseDate($dateRaw);
            $dateConfidence = $parsedDate !== null ? 'high' : 'low';
        }

        // Parse duration to minutes
        $durationMinutes = null;
        if (!empty($row['duration'])) {
            $durationMinutes = $this->parseDurationToMinutes($row['duration']);
        }

        // Build description
        $baseDescription = trim($row['description'] ?? '');
        $companions = trim($row['companions'] ?? '');

        // Generate a trip name if one isn't present
        $tripName = trim($row['trip_name'] ?? '');
        if ($tripName === '') {
            $parts = array_filter([$caveName, $parsedDate ? date('Y-m-d', strtotime($parsedDate)) : null]);
            $tripName = implode(' — ', $parts);
        }
        $tripName = mb_substr($tripName, 0, 255);

        return [
            'row' => $rowNumber,
            'cave_name_raw' => $caveName,
            'entrance_name_raw' => trim($row['entrance_name'] ?? ''),
            'date' => $parsedDate,
            'date_raw' => $dateRaw,
            'date_confidence' => $dateConfidence,
            'duration_minutes' => $durationMinutes,
            'trip_name' => $tripName,
            'description' => $baseDescription,
            'companions_raw' => $companions,
            'note' => $this->rowNote($row, $parsedDate),
        ];
    }

    /**
     * Attempt to parse various date formats to YYYY-MM-DD.
     */
    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Already ISO format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        // Common UK formats: DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY
        if (preg_match('#^(\d{1,2})[/\-\.](\d{1,2})[/\-\.](\d{4})$#', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // US format: MM/DD/YYYY — ambiguous, we assume UK
        // Year first short: YYYY/MM/DD
        if (preg_match('#^(\d{4})[/\-\.](\d{1,2})[/\-\.](\d{1,2})$#', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // DD/MM/YY two-digit year
        if (preg_match('#^(\d{1,2})[/\-\.](\d{1,2})[/\-\.](\d{2})$#', $raw, $m)) {
            $year = (int) $m[3] + ((int) $m[3] < 50 ? 2000 : 1900);

            return sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
        }

        // Try PHP's strtotime as a last resort
        $ts = @strtotime($raw);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * Convert a duration string to integer minutes.
     * Handles: "2.5", "2:30", "2h 30m", "150", "2 hours", etc.
     */
    private function parseDurationToMinutes(string $raw): ?int
    {
        $raw = trim(strtolower($raw));
        if ($raw === '') {
            return null;
        }

        // HH:MM
        if (preg_match('/^(\d+):(\d{2})$/', $raw, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        // "Xh Ym" or "X hours Y mins" or "X hr Y min"
        if (preg_match('/(\d+)\s*h[a-z]*\s*(\d+)\s*m[a-z]*/i', $raw, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        // Just hours with optional decimal: "2.5h", "2.5 hours"
        if (preg_match('/^([\d.]+)\s*h[a-z]*/i', $raw, $m)) {
            return (int) round((float) $m[1] * 60);
        }

        // Plain decimal without suffix (e.g. "2.5") → treat as hours
        if (preg_match('/^(\d+\.\d+)$/', $raw, $m)) {
            return (int) round((float) $m[1] * 60);
        }

        // Just minutes: "150", "150 min", "150 minutes"
        if (preg_match('/^(\d+)\s*(min[a-z]*)?$/i', $raw, $m)) {
            $val = (int) $m[1];

            // If it looks like raw minutes (> 10), treat as minutes; otherwise assume hours
            return $val > 10 ? $val : $val * 60;
        }

        return null;
    }

    /**
     * Parse a single CSV line respecting quoted fields.
     *
     * @return string[]
     */
    private function parseCsvLine(string $line, string $delimiter): array
    {
        // Use str_getcsv which handles quoted fields correctly
        return str_getcsv($line, $delimiter, '"', '\\');
    }

    /**
     * Generate a note for rows with low confidence or missing fields.
     *
     * @param  array<string, string>  $row
     */
    private function rowNote(array $row, ?string $parsedDate): ?string
    {
        $issues = [];

        if (($row['cave_name'] ?? '') === '') {
            $issues[] = 'cave name is missing';
        }
        if ($parsedDate === null && ($row['date'] ?? '') !== '') {
            $issues[] = "date '{$row['date']}' could not be parsed";
        }
        if ($parsedDate === null && ($row['date'] ?? '') === '') {
            $issues[] = 'date is missing';
        }

        return empty($issues) ? null : 'Needs review: '.implode('; ', $issues).'.';
    }
}
