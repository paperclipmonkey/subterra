<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\User;

class GetImportStatusTool extends ImportTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_import_status',
                'description' => "Get the user's trip import: counts by status, and what still needs the user's input, grouped "
                    .'(caves_to_resolve, people_to_resolve, other_issues, duplicates). One answer settles a whole group. '
                    .'Pass row_numbers to see those rows in full.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'row_numbers' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Optional rows to show in detail (max 20).',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $import = $this->openImport($user);
        if (!$import) {
            return ['no_import' => true, 'note' => 'Nothing staged yet. The user can upload a CSV logbook (paperclip button) or describe trips for add_trips.'];
        }

        return $this->imports->summary($import, self::rowNumbers($arguments['row_numbers'] ?? null));
    }
}
