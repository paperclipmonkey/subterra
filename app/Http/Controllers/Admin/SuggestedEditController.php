<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SuggestionApprovedMail;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Collection;
use App\Models\Route;
use App\Models\SuggestedEdit;
use App\Services\MediaSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SuggestedEditController extends Controller
{
    public function __construct(
        private readonly MediaSuggestionService $mediaSuggestionService
    ) {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $query = SuggestedEdit::with(['user', 'suggestable'])
            ->where('status', $status);

        if ($request->filled('cave_id')) {
            // Show suggestions for the cave itself AND its cave system
            $cave = Cave::find($request->query('cave_id'));
            $query->where(function ($q) use ($cave) {
                $q->where(function ($q2) use ($cave) {
                    $q2->where('suggestable_type', Cave::class)
                       ->where('suggestable_id', $cave->id);
                });
                if ($cave?->cave_system_id) {
                    $q->orWhere(function ($q2) use ($cave) {
                        $q2->where('suggestable_type', CaveSystem::class)
                           ->where('suggestable_id', $cave->cave_system_id);
                    });
                }
            });
        } elseif ($request->filled('suggestable_type') && $request->filled('suggestable_id')) {
            $query->where('suggestable_type', $request->query('suggestable_type'))
                  ->where('suggestable_id', $request->query('suggestable_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                // Search name in each possible related table (whereHas doesn't work on morphTo)
                $q->where(function ($q2) use ($search) {
                    $q2->where('suggestable_type', Cave::class)
                        ->whereIn('suggestable_id', function ($sub) use ($search) {
                            $sub->select('id')->from('caves')->where('name', 'ilike', "%{$search}%");
                        });
                })
                ->orWhere(function ($q2) use ($search) {
                    $q2->where('suggestable_type', CaveSystem::class)
                        ->whereIn('suggestable_id', function ($sub) use ($search) {
                            $sub->select('id')->from('cave_systems')->where('name', 'ilike', "%{$search}%");
                        });
                })
                ->orWhere(function ($q2) use ($search) {
                    $q2->where('suggestable_type', Route::class)
                        ->whereIn('suggestable_id', function ($sub) use ($search) {
                            $sub->select('id')->from('routes')->where('name', 'ilike', "%{$search}%");
                        });
                })
                ->orWhereRaw('suggested_data::text ilike ?', ["%{$search}%"]);
            });
        }

        return $query->latest()->paginate(20);
    }

    public function show(SuggestedEdit $suggestedEdit)
    {
        $suggestedEdit->load(['user', 'suggestable']);

        // Eager load relationships for the suggestable model to ensure accurate diffs
        if ($suggestedEdit->suggestable) {
            $type = $suggestedEdit->suggestable_type;
            if ($type === Cave::class) {
                // Cave model uses 'system' relationship
                $suggestedEdit->suggestable->load(['system', 'tags']);
            } elseif ($type === Route::class) {
                // Route model uses 'caveSystem' relationship
                $suggestedEdit->suggestable->load(['caveSystem', 'entrance', 'exit', 'tags']);
            } elseif ($type === CaveSystem::class) {
                $suggestedEdit->suggestable->load(['files']);
            }
        }

        return $suggestedEdit;
    }

    public function approve(Request $request, SuggestedEdit $suggestedEdit)
    {
        // Partial approval: if specific fields are provided, only approve those
        $selectedFields = $request->input('fields');

        $allSuggestedData = $suggestedEdit->suggested_data;
        $allOriginalData = $suggestedEdit->original_data ?? [];

        if (is_array($selectedFields) && !empty($selectedFields)) {
            // Split data into approved and remaining
            $approvedData = array_intersect_key($allSuggestedData, array_flip($selectedFields));
            $remainingData = array_diff_key($allSuggestedData, array_flip($selectedFields));
            $remainingOriginal = array_intersect_key($allOriginalData, $remainingData);
        } else {
            $approvedData = $allSuggestedData;
            $remainingData = [];
            $remainingOriginal = [];
        }

        // Update the suggestion to contain only the approved fields
        $suggestedEdit->suggested_data = $approvedData;
        $suggestedEdit->original_data = array_intersect_key($allOriginalData, $approvedData);
        $suggestedEdit->save();

        $targetDirMap = [
            Cave::class => 'caves',
            CaveSystem::class => 'cave_systems',
            Route::class => 'routes',
            Collection::class => 'collections',
        ];

        $targetDir = $targetDirMap[$suggestedEdit->suggestable_type] ?? 'misc';

        // Promote pending media to permanent storage
        $promotedData = $this->mediaSuggestionService->promotePendingMedia(
            $suggestedEdit->suggested_data,
            $targetDir
        );
        $suggestedEdit->suggested_data = $promotedData;
        $suggestedEdit->save();

        if ($suggestedEdit->suggestable_id) {
            // Apply changes to the existing target model
            $data = $suggestedEdit->suggested_data;
            if ($suggestedEdit->suggestable_type === Cave::class) {
                $this->handleCaveMediaApproval($suggestedEdit->suggestable, $data);
                unset($data['hero_image'], $data['entrance_image']);
            } elseif ($suggestedEdit->suggestable_type === CaveSystem::class) {
                $this->handleCaveSystemFiles($suggestedEdit->suggestable, $data);
                unset($data['media'], $data['deleted_files']);
            }
            $suggestedEdit->suggestable->update($data);
        } else {
            // Create new item
            $modelClass = $suggestedEdit->suggestable_type;

            if (class_exists($modelClass)) {
                $data = $suggestedEdit->suggested_data;
                // Auto-generate slug if missing and name is present
                if (empty($data['slug']) && !empty($data['name'])) {
                    $data['slug'] = Str::slug($data['name']);
                }

                $caveMedia = [];
                $systemFiles = [];

                if ($modelClass === Cave::class) {
                    $caveMedia['hero'] = $data['hero_image'] ?? null;
                    $caveMedia['entrance'] = $data['entrance_image'] ?? null;
                    unset($data['hero_image'], $data['entrance_image']);
                } elseif ($modelClass === CaveSystem::class) {
                    $systemFiles = $data['media'] ?? [];
                    unset($data['media'], $data['deleted_files']);
                }

                $newItem = $modelClass::create($data);

                if ($modelClass === Cave::class) {
                    $this->handleCaveMediaApproval($newItem, $caveMedia);
                } elseif ($modelClass === CaveSystem::class) {
                    $this->handleCaveSystemFiles($newItem, ['media' => $systemFiles]);
                }

                // Update the suggested edit to point to the newly created item
                $suggestedEdit->suggestable_id = $newItem->id;
                $suggestedEdit->save();
            }
        }

        $suggestedEdit->update(['status' => 'approved']);

        // Create a new pending suggestion for any remaining unapproved fields
        if (!empty($remainingData)) {
            SuggestedEdit::create([
                'user_id' => $suggestedEdit->user_id,
                'suggestable_type' => $suggestedEdit->suggestable_type,
                'suggestable_id' => $suggestedEdit->suggestable_id,
                'original_data' => $remainingOriginal,
                'suggested_data' => $remainingData,
                'status' => 'pending',
            ]);
        }

        if ($suggestedEdit->user) {
            Mail::to($suggestedEdit->user)->send(new SuggestionApprovedMail($suggestedEdit));
        }

        return response()->json(['message' => 'Suggestion approved and applied.']);
    }

    public function reject(Request $request, SuggestedEdit $suggestedEdit)
    {
        // Clean up any pending media files
        $this->mediaSuggestionService->cleanUpPendingMedia($suggestedEdit->suggested_data);

        $suggestedEdit->update([
            'status' => 'rejected',
            'admin_comment' => $request->input('admin_comment'),
        ]);

        return response()->json(['message' => 'Suggestion rejected.']);
    }

    private function handleCaveMediaApproval(Cave $cave, array $data): void
    {
        foreach (['hero', 'entrance'] as $type) {
            $key = $type.'_image';
            if (isset($data[$key])) {
                $imageData = $data[$key];

                if (is_array($imageData)) {
                    $cave->media()->updateOrCreate(
                        ['type' => $type],
                        [
                            'filename' => $imageData['data'] ?? $imageData['filename'] ?? null,
                            'title' => $imageData['title'] ?? null,
                            'photographer' => $imageData['photographer'] ?? null,
                            'copyright' => $imageData['copyright'] ?? null,
                        ]
                    );
                } elseif (is_string($imageData)) {
                    // Simple string path (backward compatibility or simple mock)
                    $cave->media()->updateOrCreate(
                        ['type' => $type],
                        ['filename' => $imageData]
                    );
                }
            }
        }
    }

    private function handleCaveSystemFiles(CaveSystem $system, array $data): void
    {
        // Handle deletions
        if (!empty($data['deleted_files'])) {
            $filesToDelete = $system->files()->whereIn('id', $data['deleted_files'])->get();
            foreach ($filesToDelete as $file) {
                \Illuminate\Support\Facades\Storage::disk('media')->delete("cave_system_files/{$system->id}/{$file->filename}");
                $file->delete();
            }
        }

        // Handle new files (media)
        if (!empty($data['media'])) {
            foreach ($data['media'] as $mediaItem) {
                if (isset($mediaItem['data'])) {
                    $tempPath = $mediaItem['data']; // This is the path returned by promotePendingMedia (e.g. cave_systems/file.webp)
                    $filename = basename($tempPath);
                    $newPath = "cave_system_files/{$system->id}/{$filename}";

                    // Move file to correct directory
                    if (\Illuminate\Support\Facades\Storage::disk('media')->exists($tempPath)) {
                        \Illuminate\Support\Facades\Storage::disk('media')->move($tempPath, $newPath);

                        $system->files()->create([
                            'filename' => $filename,
                            'original_filename' => $mediaItem['name'] ?? $filename,
                            'details' => $mediaItem['details'] ?? null,
                            'mime_type' => $mediaItem['mime_type'] ?? 'application/octet-stream',
                            'size' => $mediaItem['size'] ?? 0,
                        ]);
                    }
                }
            }
        }
    }
}
