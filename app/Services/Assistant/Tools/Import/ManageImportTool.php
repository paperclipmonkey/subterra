<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\User;

class ManageImportTool extends ImportTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'manage_import',
                'description' => 'set_default_visibility: who can see the imported trips (public, club or private; rows set individually keep theirs). '
                    .'discard: abandon everything not yet imported — only when the user explicitly asks.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => ['type' => 'string', 'enum' => ['set_default_visibility', 'discard']],
                        'visibility' => ['type' => 'string', 'enum' => ['public', 'club', 'private']],
                    ],
                    'required' => ['action'],
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

        return match ($arguments['action'] ?? null) {
            'set_default_visibility' => $this->imports->setDefaultVisibility($import, $user, (string) ($arguments['visibility'] ?? '')),
            'discard' => $this->imports->discard($import),
            default => ['error' => 'action must be set_default_visibility or discard.'],
        };
    }
}
