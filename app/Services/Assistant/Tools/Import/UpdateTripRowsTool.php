<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\User;

class UpdateTripRowsTool extends ImportTool
{
    private const FIELDS = ['date', 'start_time', 'duration_minutes', 'name', 'description', 'visibility', 'skip', 'allow_duplicate', 'add_companions'];

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'update_trip_rows',
                'description' => 'Correct or skip staged trips by row number, using what the user told you. '
                    .'skip=true leaves a row out of the import (e.g. cave not in Subterra, or a duplicate); skip=false brings it back. '
                    .'allow_duplicate=true imports a row even though a similar trip exists.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'row_numbers' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'start_time' => ['type' => 'string', 'description' => 'HH:MM, 24 hour'],
                        'duration_minutes' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'description' => "Replaces the notes. Only the user's own words."],
                        'visibility' => ['type' => 'string', 'enum' => ['public', 'club', 'private']],
                        'skip' => ['type' => 'boolean'],
                        'allow_duplicate' => ['type' => 'boolean'],
                        'add_companions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['row_numbers'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $import = $this->openImport($user);
        if (!$import) {
            return $this->noImport();
        }

        $rowNumbers = self::rowNumbers($arguments['row_numbers'] ?? null);
        if ($rowNumbers === null) {
            return ['error' => 'row_numbers is required.'];
        }

        $changes = array_intersect_key($arguments, array_flip(self::FIELDS));
        if ($changes === []) {
            return ['error' => 'Nothing to change.'];
        }

        $result = $this->imports->updateRows($import, $user, $rowNumbers, $changes);
        if (isset($result['rows']) && count($result['rows']) > 10) {
            $result['rows_updated'] = count($result['rows']);
            $result['rows'] = array_slice($result['rows'], 0, 10);
        }

        return $result + ['counts' => $this->imports->counts($import)];
    }
}
