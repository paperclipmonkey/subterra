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

    public function show($id)
    {
        return new PageResource(Page::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $page = Page::findOrFail($id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,'.$page->id,
            'content' => 'nullable|string',
        ]);

        $page->update($validated + ['user_id' => $request->user()->id]);

        return new PageResource($page);
    }

    public function destroy($id)
    {
        $page = Page::findOrFail($id);
        $page->delete();

        return response()->noContent();
    }

    public function publicShow(Page $page)
    {
        $page->increment('access_count');

        return new PageResource($page);
    }
}
