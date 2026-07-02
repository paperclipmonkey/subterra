<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        // Files are named by ISO date (YYYY-MM-DD.md), so an explicit
        // descending string sort gives newest-first regardless of the
        // order the filesystem happens to list them in.
        $files = collect(Storage::disk('news')->files())
            ->filter(fn ($file) => str_ends_with($file, '.md'))
            ->sortDesc(SORT_STRING)
            ->values();

        $newsContent = [];
        foreach ($files as $file) {
            $date = str_replace('.md', '', $file);
            $content = Storage::disk('news')->get($file);

            // Extract title (first line starting with #)
            $title = $date;
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $title = trim($matches[1]);
            }

            $newsContent[] = [
                'id' => $date,
                'date' => $date,
                'title' => $title,
                'content' => $content,
            ];
        }

        return response()->json($newsContent);
    }

    public function show($id): JsonResponse
    {
        $filename = $id.'.md';
        if (!Storage::disk('news')->exists($filename)) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $content = Storage::disk('news')->get($filename);

        // Extract title
        $title = $id;
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            $title = trim($matches[1]);
        }

        return response()->json([
            'id' => $id,
            'date' => $id,
            'title' => $title,
            'content' => $content,
        ]);
    }
}
