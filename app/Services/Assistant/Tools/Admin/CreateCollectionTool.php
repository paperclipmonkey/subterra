<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\Collection;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\Assistant\Tools\Admin\Concerns\ResolvesCollectionCaves;
use Illuminate\Support\Str;

class CreateCollectionTool implements AssistantTool
{
    use ResolvesCollectionCaves;

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_collection',
                'description' => 'Create a new curated cave collection — a themed list of caves users can work '
                    .'through (e.g. "Yorkshire Big Three"). This makes a LIVE change immediately; collections are '
                    .'not part of the suggested-edit review queue. Confirm the name and the list of caves with the '
                    .'admin before calling. Pass caves by their slug (from search_caves or list_collections). '
                    .'Returns the new collection id, slug, and URL.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name of the collection (max 255 chars). e.g. "Mendip Classics".',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optional description shown on the collection page.',
                        ],
                        'caves' => [
                            'type' => 'array',
                            'description' => 'Optional ordered list of caves to add. Each entry is an object with a '
                                .'"slug" and an optional "note" (per-cave description shown in the collection).',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'slug' => ['type' => 'string', 'description' => 'Cave slug, e.g. "swildons-hole".'],
                                    'note' => ['type' => 'string', 'description' => 'Optional per-cave note.'],
                                ],
                                'required' => ['slug'],
                            ],
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $name = trim((string) ($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'A collection name is required.'];
        }
        $name = mb_substr($name, 0, 255);

        $slug = Str::slug($name);
        if ($slug === '') {
            return ['error' => 'That name does not produce a valid collection slug. Try a different name.'];
        }
        if (Collection::where('slug', $slug)->exists()) {
            return ['error' => "A collection with the slug \"{$slug}\" already exists. Pick a different name or edit the existing one with update_collection."];
        }

        [$syncData, $unknownSlugs] = $this->resolveCaves($arguments['caves'] ?? []);

        $collection = Collection::create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => isset($arguments['description']) ? (string) $arguments['description'] : null,
        ]);

        if ($syncData !== []) {
            $collection->caves()->sync($syncData);
        }

        $result = [
            'success' => true,
            'collection_id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'url' => "/collections/{$collection->slug}",
            'cave_count' => count($syncData),
            'message' => 'Collection created. This is a live change — it is visible to users immediately.',
        ];

        if ($unknownSlugs !== []) {
            $result['unknown_cave_slugs'] = array_values($unknownSlugs);
            $result['note'] = 'Some cave slugs were not found and were skipped: '.implode(', ', $unknownSlugs);
        }

        return $result;
    }
}
