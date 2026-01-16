<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $newsContent = [];
        foreach (array_reverse(\Storage::disk('news')->files()) as $file) {
            // Skip non-markdown files
            if (!str_ends_with($file, '.md')) continue;

            $date = str_replace('.md', '', $file);
            $content = \Storage::disk('news')->get($file);
            
            // Extract title (first line starting with #)
            $title = $date;
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $title = trim($matches[1]);
            }

            // Remove title from content preview if needed, or just send full content
            // For the list, we might want a snippet, but let's send full for now or strict snippet
            
            $newsContent[] = [
                'id' => $date, // Using filename (date) as ID
                'date' => $date,
                'title' => $title,
                'content' => $content, // Sending full content for now, frontend can truncate
            ];
        }
        return response()->json($newsContent);
    }

    public function show($id): JsonResponse
    {
        $filename = $id . '.md';
        if (!\Storage::disk('news')->exists($filename)) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $content = \Storage::disk('news')->get($filename);
        
        // Extract title
        $title = $id;
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            $title = trim($matches[1]);
        }

        return response()->json([
            'id' => $id,
            'date' => $id,
            'title' => $title,
            'content' => $content
        ]);
    }
}
