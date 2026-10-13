<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\User;
use App\Services\TripImport\LogbookParser;

class AddTripsTool extends ImportTool
{
    private const MAX_PER_CALL = 25;

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'add_trips',
                'description' => 'Stage past trips the user has described in chat (not for uploaded files — those are staged already). '
                    .'Caves and companions are matched automatically; the result says what still needs checking. '
                    .'Nothing is saved as a trip until import_ready_trips. Only include details the user actually gave.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'trips' => [
                            'type' => 'array',
                            'description' => 'Up to 25 trips.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'cave' => ['type' => 'string', 'description' => 'Cave or system name as the user said it.'],
                                    'entrance' => ['type' => 'string', 'description' => 'Entrance used, if given.'],
                                    'exit' => ['type' => 'string', 'description' => 'Exit entrance for a through trip, if given.'],
                                    'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Work out relative dates ("last Saturday") from the current date.'],
                                    'start_time' => ['type' => 'string', 'description' => 'HH:MM, 24 hour, if given.'],
                                    'duration_minutes' => ['type' => 'integer'],
                                    'companions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Names of the other people, as given. Not the user.'],
                                    'name' => ['type' => 'string', 'description' => 'Trip title, only if the user gave one.'],
                                    'description' => ['type' => 'string', 'description' => "The user's own words about the trip. Never write this yourself."],
                                ],
                                'required' => ['cave', 'date'],
                            ],
                        ],
                    ],
                    'required' => ['trips'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $trips = array_values(array_filter((array) ($arguments['trips'] ?? []), 'is_array'));
        if ($trips === []) {
            return ['error' => 'No trips given.'];
        }
        $ignored = max(0, count($trips) - self::MAX_PER_CALL);
        $trips = array_slice($trips, 0, self::MAX_PER_CALL);

        $rows = array_map(function (array $t): array {
            $dateRaw = trim((string) ($t['date'] ?? ''));

            return [
                'cave_name' => mb_substr(trim((string) ($t['cave'] ?? '')), 0, 255),
                'entrance_name' => mb_substr(trim((string) ($t['entrance'] ?? '')), 0, 255),
                'exit_name' => mb_substr(trim((string) ($t['exit'] ?? '')), 0, 255),
                'date' => LogbookParser::parseDate($dateRaw),
                'date_raw' => mb_substr($dateRaw, 0, 255),
                'start_time' => LogbookParser::parseTime((string) ($t['start_time'] ?? '')),
                'duration_minutes' => isset($t['duration_minutes']) && is_numeric($t['duration_minutes'])
                    ? max(0, min((int) $t['duration_minutes'], 14 * 24 * 60)) : null,
                'name' => mb_substr(trim((string) ($t['name'] ?? '')), 0, 255),
                'description' => mb_substr(trim((string) ($t['description'] ?? '')), 0, 10000),
                'companions' => array_slice(array_map('strval', array_filter((array) ($t['companions'] ?? []), 'is_scalar')), 0, 30),
            ];
        }, $trips);

        $import = $this->imports->openOrCreate($user);
        $result = $this->imports->addRows($import, $user, $rows, 'chat');

        $added = $import->rows()->whereIn('row_number', $result['row_numbers'])->orderBy('row_number')->get();

        return array_filter([
            'added' => count($result['row_numbers']),
            'rows' => $added->map(fn ($r) => $this->imports->rowBrief($r))->values()->all(),
            'rejected_import_full' => $result['rejected'] ?: null,
            'ignored_over_limit' => $ignored ?: null,
            'counts' => $this->imports->counts($import),
        ], fn ($v) => $v !== null);
    }
}
