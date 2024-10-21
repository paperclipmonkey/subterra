<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserDetailResource;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index()
    {
        $files = \Storage::disk('news')->files(); // Get a list of all files in the filesystem 'news' driver
        $newsContent = [];
        foreach ($files as $file) {
            $stripped = str_replace('.md', '', $file);
            $newsContent[($stripped)] = \Storage::disk('news')->get(path: $file);
        }
        return response()->json($newsContent);
    }
}
