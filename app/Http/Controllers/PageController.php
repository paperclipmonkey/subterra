<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        return Page::orderBy('title')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages',
            'content' => 'nullable|string',
        ]);

        $page = Page::create($validated + ['user_id' => $request->user()->id]);

        return response()->json($page, 201);
    }

    public function show(Page $page)
    {
        return $page;
    }

    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,' . $page->id,
            'content' => 'nullable|string',
        ]);

        $page->update($validated + ['user_id' => $request->user()->id]);

        return $page;
    }

    public function destroy(Page $page)
    {
        $page->delete();
        return response()->noContent();
    }

    public function publicShow($slug)
    {
        $page = Page::where('slug', $slug)->firstOrFail();
        $page->increment('access_count');
        return $page;
    }
}
