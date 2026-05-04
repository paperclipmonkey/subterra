<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagsController extends Controller
{
    public function index(): JsonResponse
    {
        $categoryOrder = ['curated', 'region', 'type', 'access', 'tackle', 'previously done'];

        $tags = Tag::all()
            ->groupBy('category')
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
