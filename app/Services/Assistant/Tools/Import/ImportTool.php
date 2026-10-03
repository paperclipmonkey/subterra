<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\TripImport;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\TripImport\TripImportService;

/**
 * Base for the trip-import tools. Every tool acts only on the calling user's
 * own open import — there is no way to name another user's import.
 */
abstract class ImportTool implements AssistantTool
{
    public function __construct(protected readonly TripImportService $imports)
    {
    }

    protected function openImport(User $user): ?TripImport
    {
        return $this->imports->openImportFor($user);
    }

    /** @return array<string, string> */
    protected function noImport(): array
    {
        return ['error' => 'There is no import in progress. Ask the user to upload their logbook (paperclip button) or describe their trips, then use add_trips.'];
    }

    /**
     * @return int[]|null
     */
    protected static function rowNumbers(mixed $value): ?array
    {
        if (!is_array($value) || $value === []) {
            return null;
        }

        return array_values(array_slice(array_map('intval', $value), 0, 500));
    }
}
