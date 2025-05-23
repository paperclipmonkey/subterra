<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $newsContent = [];
        foreach (array_reverse(\Storage::disk('news')->files()) as $file) {
            $title = str_replace('.md', '', $file);
            $newsContent[($title)] = \Storage::disk('news')->get(path: $file);
        }
        return response()->json($newsContent);
    }
}
