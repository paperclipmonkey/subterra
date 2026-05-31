<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagsController extends Controller
{
    public function index(): JsonResponse
    {
        $categoryOrder = ['curated', 'region', 'type', 'access', 'tackle', 'system length', 'previously done'];

        $systemLengthOrder = ['< 250m', '> 250m', '> 500m', '> 1km', '> 5km'];

        $tags = Tag::all()
            ->groupBy('category')
            ->map(function ($group, $category) use ($systemLengthOrder) {
                if ($category === 'system length') {
                    return $group->sortBy(function ($tag) use ($systemLengthOrder) {
                        $idx = array_search($tag->tag, $systemLengthOrder, true);

                        return $idx === false ? PHP_INT_MAX : $idx;
                    })->values();
                }

                return $group;
            })
            ->sortKeysUsing(function (string $a, string $b) use ($categoryOrder) {
                $posA = array_search($a, $categoryOrder, true);
                $posB = array_search($b, $categoryOrder, true);
                $posA = $posA === false ? PHP_INT_MAX : $posA;
                $posB = $posB === false ? PHP_INT_MAX : $posB;

                return $posA - $posB;
            });

        return response()->json($tags);
    }
}
