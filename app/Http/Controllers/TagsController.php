<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagsController extends Controller
{
    public function index(): JsonResponse
    {
        $tags = Tag::all()->groupBy('category');

        return response()->json($tags);
    }
}
