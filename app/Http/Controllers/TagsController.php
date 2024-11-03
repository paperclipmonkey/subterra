<?php

namespace App\Http\Controllers;

class TagsController extends Controller
{
    public function index()
    {
        $tags = \App\Models\Tag::all()->groupBy('category');
        return response()->json($tags);
    }
}
