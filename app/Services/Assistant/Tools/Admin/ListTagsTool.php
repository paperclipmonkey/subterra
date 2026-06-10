<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Facades\DB;

class ListTagsTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_tags',
                'description' => 'List every tag in the database with its ID, category, and how many caves and '
                    .'cave systems currently carry it. Always call this before proposing tag changes so you use '
                    .'real tag IDs — never guess tag names or IDs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'Optional category filter (e.g. "region", "curated", "access", "tackle").',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $category = isset($arguments['category']) ? trim((string) $arguments['category']) : null;

        $query = DB::table('tags')
            ->leftJoin('cave_tag', 'cave_tag.tag_id', '=', 'tags.id')
            ->leftJoin('cave_system_tag', 'cave_system_tag.tag_id', '=', 'tags.id')
            ->select([
                'tags.id',
                'tags.tag',
                'tags.type',
                'tags.category',
                'tags.assignable',
                DB::raw('COUNT(DISTINCT cave_tag.cave_id) as cave_count'),
                DB::raw('COUNT(DISTINCT cave_system_tag.cave_system_id) as system_count'),
            ])
            ->groupBy('tags.id', 'tags.tag', 'tags.type', 'tags.category', 'tags.assignable')
            ->orderBy('tags.category')
            ->orderBy('tags.tag');

        if ($category) {
            $query->whereRaw('LOWER(tags.category) = ?', [mb_strtolower($category)]);
        }

        $tags = $query->get()->map(fn ($t) => [
            'id' => $t->id,
            'tag' => $t->tag,
            'type' => $t->type,
            'category' => $t->category,
            'assignable' => (bool) $t->assignable,
            'cave_count' => (int) $t->cave_count,
            'system_count' => (int) $t->system_count,
        ])->values();

        return [
            'count' => $tags->count(),
            'tags' => $tags,
        ];
    }
}
