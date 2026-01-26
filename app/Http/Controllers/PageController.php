<?php

namespace App\Http\Controllers;

use App\Http\Resources\PageResource;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        return PageResource::collection(Page::orderBy('title')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages',
            'content' => 'nullable|string',
        ]);

        $page = Page::create($validated + ['user_id' => $request->user()->id]);

        return new PageResource($page);
    }

    public function show(Page $page)
    {
        return new PageResource($page);
    }

    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,' . $page->id,
            'content' => 'nullable|string',
        ]);

        $page->update($validated + ['user_id' => $request->user()->id]);

        return new PageResource($page);
    }

    public function destroy(Page $page)
    {
        $page->delete();
        return response()->noContent();
    }

    public function publicShow(Page $page)
    {
        $page->increment('access_count');
        return new PageResource($page);
    }
}
