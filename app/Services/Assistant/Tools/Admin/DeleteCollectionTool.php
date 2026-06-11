<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\Collection;
use App\Models\User;
use App\Services\Assistant\AssistantTool;

class DeleteCollectionTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'delete_collection',
                'description' => 'Permanently delete a collection. Identify it by collection_id or slug (from '
                    .'list_collections / get_collection_details). This makes a LIVE change immediately and CANNOT '
                    .'be undone — the caves themselves are not deleted, only the collection that grouped them. '
                    .'Always confirm the exact collection with the admin before calling.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'collection_id' => [
                            'type' => 'integer',
                            'description' => 'Numeric ID of the collection to delete.',
                        ],
                        'slug' => [
                            'type' => 'string',
                            'description' => 'Alternatively, the collection slug, e.g. "mendip-classics".',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $collection = null;
        if (!empty($arguments['collection_id'])) {
            $collection = Collection::find((int) $arguments['collection_id']);
        } elseif (!empty($arguments['slug'])) {
            $collection = Collection::where('slug', (string) $arguments['slug'])->first();
        }

        if (!$collection) {
            return ['error' => 'Collection not found. Pass either collection_id or slug from list_collections.'];
        }

        $name = $collection->name;
        $slug = $collection->slug;

        // Detach pivot rows explicitly so we never leave orphaned cave_collection
        // entries if the FK cascade is ever relaxed; the caves themselves remain.
        $collection->caves()->detach();
        $collection->delete();

        return [
            'success' => true,
            'deleted_collection' => $name,
            'slug' => $slug,
            'message' => "Deleted the \"{$name}\" collection. The caves it contained are unaffected.",
        ];
    }
}
