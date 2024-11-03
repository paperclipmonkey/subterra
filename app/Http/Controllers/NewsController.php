<?php

namespace App\Http\Controllers;

class NewsController extends Controller
{
    public function index()
    {
        $newsContent = [];
        foreach (\Storage::disk('news')->files() as $file) {
            $title = str_replace('.md', '', $file);
            $newsContent[($title)] = \Storage::disk('news')->get(path: $file);
        }
        return response()->json($newsContent);
    }
}
