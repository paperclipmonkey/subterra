<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\Collection;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\Assistant\Tools\Admin\Concerns\ResolvesCollectionCaves;

class UpdateCollectionTool implements AssistantTool
{
    use ResolvesCollectionCaves;

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'update_collection',
                'description' => 'Edit an existing collection: rename it, change its description, or set the caves '
                    .'it contains. Identify the collection by collection_id or slug (from list_collections / '
                    .'get_collection_details). This makes a LIVE change immediately. If you pass "caves", it '
                    .'REPLACES the entire cave list with the one you provide, in the order given — pass the full '
                    .'desired list, not just additions. Omit "caves" to leave the existing caves untouched. The '
                    .'slug does not change when you rename a collection (existing links keep working).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'collection_id' => [
                            'type' => 'integer',
                            'description' => 'Numeric ID of the collection to edit.',
                        ],
                        'slug' => [
                            'type' => 'string',
                            'description' => 'Alternatively, the collection slug, e.g. "mendip-classics".',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'New name (max 255 chars). Omit to leave unchanged.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'New description. Pass an empty string to clear it. Omit to leave unchanged.',
                        ],
                        'caves' => [
                            'type' => 'array',
                            'description' => 'Optional. Replaces the full cave list. Each entry is an object with a '
                                .'"slug" and an optional "note". Pass an empty array to remove all caves.',
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
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $collection = $this->resolveCollection($arguments);
        if (!$collection) {
            return ['error' => 'Collection not found. Pass either collection_id or slug from list_collections.'];
        }

        $updates = [];

        if (array_key_exists('name', $arguments)) {
            $name = mb_substr(trim((string) $arguments['name']), 0, 255);
            if ($name === '') {
                return ['error' => 'A collection name cannot be blank. Omit "name" to leave it unchanged.'];
            }
            $updates['name'] = $name;
        }

        if (array_key_exists('description', $arguments)) {
            $updates['description'] = $arguments['description'] !== null
                ? (string) $arguments['description']
                : null;
        }

        if ($updates !== []) {
            $collection->update($updates);
        }

        $unknownSlugs = [];
        $cavesChanged = array_key_exists('caves', $arguments);
        if ($cavesChanged) {
            [$syncData, $unknownSlugs] = $this->resolveCaves($arguments['caves']);
            $collection->caves()->sync($syncData);
        }

        $collection->refresh();

        $result = [
            'success' => true,
            'collection_id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'url' => "/collections/{$collection->slug}",
            'cave_count' => $collection->caves()->count(),
            'fields_updated' => array_merge(array_keys($updates), $cavesChanged ? ['caves'] : []),
            'message' => 'Collection updated. This is a live change — it is visible to users immediately.',
        ];

        if ($unknownSlugs !== []) {
            $result['unknown_cave_slugs'] = array_values($unknownSlugs);
            $result['note'] = 'Some cave slugs were not found and were skipped: '.implode(', ', $unknownSlugs);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function resolveCollection(array $arguments): ?Collection
    {
        if (!empty($arguments['collection_id'])) {
            return Collection::find((int) $arguments['collection_id']);
        }
        if (!empty($arguments['slug'])) {
            return Collection::where('slug', (string) $arguments['slug'])->first();
        }

        return null;
    }
}
