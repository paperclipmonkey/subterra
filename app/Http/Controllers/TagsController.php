<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserDetailResource;
use Illuminate\Http\Request;

class TagsController extends Controller
{
    public function index()
    {
        $tags = \App\Models\Tag::all()->groupBy('category');
        return response()->json($tags);
    }
}
