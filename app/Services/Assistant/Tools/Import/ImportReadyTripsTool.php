<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\User;

class ImportReadyTripsTool extends ImportTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'import_ready_trips',
                'description' => "Save the import's ready rows as trips. ONLY call this after telling the user how many trips "
                    .'will be imported and their visibility, and the user has clearly said yes in their latest message. '
                    .'Rows that still need review, duplicates and skipped rows are never imported.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'confirmed' => ['type' => 'boolean', 'description' => 'true only if the user explicitly confirmed.'],
                        'row_numbers' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Optional: only these ready rows.'],
                    ],
                    'required' => ['confirmed'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        if (($arguments['confirmed'] ?? false) !== true) {
            return ['error' => 'Not confirmed. Tell the user how many trips are ready and ask them to confirm first.'];
        }

        $import = $this->openImport($user);
        if (!$import) {
            return $this->noImport();
        }

        $counts = $this->imports->counts($import);
        if ($counts['ready'] === 0) {
            return ['error' => 'No rows are ready to import yet.', 'counts' => $counts];
        }

        return $this->imports->commit($import, $user, self::rowNumbers($arguments['row_numbers'] ?? null));
    }
}
