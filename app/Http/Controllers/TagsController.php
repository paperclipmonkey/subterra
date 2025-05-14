<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class TagsController extends Controller
{
    public function index(): JsonResponse
    {
        $tags = \App\Models\Tag::all()->groupBy('category');
        return response()->json($tags);
    }
}
