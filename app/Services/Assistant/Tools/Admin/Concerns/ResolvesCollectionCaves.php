<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin\Concerns;

use App\Models\Cave;

/**
 * Shared cave-slug resolution for the collection steward tools. The model works
 * in slugs (the read tools return them), so the create/update tools accept an
 * ordered list of {slug, note} entries and turn it into pivot sync data.
 */
trait ResolvesCollectionCaves
{
    /**
     * Resolve an ordered array of {slug, note} cave entries into sync data keyed
     * by cave id, preserving order via sort_order. Bare strings are accepted as
     * slugs too. Returns [syncData, unknownSlugs].
     *
     * @param  mixed  $caves
     * @return array{0: array<int, array{description: ?string, sort_order: int}>, 1: array<int, string>}
     */
    protected function resolveCaves(mixed $caves): array
    {
        if (!is_array($caves) || $caves === []) {
            return [[], []];
        }

        $slugs = [];
        foreach ($caves as $entry) {
            $slug = $this->caveSlug($entry);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        $found = Cave::whereIn('slug', $slugs)->pluck('id', 'slug');

        $syncData = [];
        $unknown = [];
        $order = 0;
        foreach ($caves as $entry) {
            $slug = $this->caveSlug($entry);
            if ($slug === '') {
                continue;
            }
            if (!isset($found[$slug])) {
                $unknown[$slug] = $slug;

                continue;
            }
            $note = is_array($entry) && isset($entry['note']) ? (string) $entry['note'] : null;
            $syncData[(int) $found[$slug]] = ['description' => $note, 'sort_order' => $order++];
        }

        return [$syncData, $unknown];
    }

    private function caveSlug(mixed $entry): string
    {
        return is_array($entry) ? trim((string) ($entry['slug'] ?? '')) : trim((string) $entry);
    }
}
