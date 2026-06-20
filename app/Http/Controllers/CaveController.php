<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Requests\UpdateCaveRequest;
use App\Http\Resources\CaveResource;
use App\Models\Cave;
use App\Models\Tag;
use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaveController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {
    }

    public function index(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $hasApprovedClub = false;
        if ($user) {
            $user->load('clubs');
            $hasApprovedClub = $user->hasApprovedClub();
        }

        // Pre-fetch visited system IDs
        $visitedSystemIds = [];
        if ($user) {
            $visitedSystemIds = DB::table('trip_user')
                ->join('trips', 'trips.id', '=', 'trip_user.trip_id')
                ->where('trip_user.user_id', $user->id)
                ->distinct()
                ->pluck('trips.cave_system_id')
                ->flip();
        }

        // 1. Caves as raw rows — optionally filtered to curated-only for fast initial load
        $cavesQuery = DB::table('caves')
            ->select(['caves.id', 'caves.slug', 'caves.name', 'caves.location_name', 'caves.location_country', 'caves.location_lat', 'caves.location_lng', 'caves.cave_system_id']);

        // Never surface soft-deleted or admin_only caves on the public list.
        \App\Support\CaveVisibility::publicOnly($cavesQuery);

        if ($request->boolean('curated')) {
            $curatedTagId = DB::table('tags')
                ->where('tag', 'Curated')
                ->where('category', 'curated')
                ->value('id');
            if ($curatedTagId) {
                $cavesQuery->join('cave_tag as ct_curated', function ($join) use ($curatedTagId) {
                    $join->on('caves.id', '=', 'ct_curated.cave_id')
                        ->where('ct_curated.tag_id', '=', $curatedTagId);
                });
            }
        }

        $caves = $cavesQuery->orderBy('caves.name')->get();

        $caveIds = $caves->pluck('id');
        $systemIds = $caves->pluck('cave_system_id')->unique()->filter();

        // 2. Media (hero, entrance, hero_video only)
        $mediaBycave = DB::table('cave_media')
            ->select(['cave_id', 'type', 'filename'])
            ->whereIn('cave_id', $caveIds)
            ->whereIn('type', ['hero', 'entrance', 'hero_video'])
            ->get()
            ->groupBy('cave_id');

        // 3. Cave tags via pivot
        $caveTags = DB::table('cave_tag')
            ->join('tags', 'tags.id', '=', 'cave_tag.tag_id')
            ->select(['cave_tag.cave_id', 'tags.id', 'tags.tag', 'tags.category'])
            ->whereIn('cave_tag.cave_id', $caveIds)
            ->get()
            ->groupBy('cave_id');

        // 4. Systems
        $systems = DB::table('cave_systems')
            ->select(['id', 'name', 'length', 'vertical_range'])
            ->whereIn('id', $systemIds)
            ->get()
            ->keyBy('id');

        // 5. System tags via pivot
        $systemTags = DB::table('cave_system_tag')
            ->join('tags', 'tags.id', '=', 'cave_system_tag.tag_id')
            ->select(['cave_system_tag.cave_system_id', 'tags.id', 'tags.tag', 'tags.category'])
            ->whereIn('cave_system_tag.cave_system_id', $systemIds)
            ->get()
            ->groupBy('cave_system_id');

        // 6. Cached special tags (Previously Done, Not Done Yet, length tags)
        $specialTags = DB::table('tags')
            ->select(['id', 'tag', 'category'])
            ->whereIn('tag', ['Previously Done', 'Not Done Yet', '> 5km', '> 1km', '> 500m', '> 250m', '< 250m'])
            ->get()
            ->keyBy('tag');

        // Media URL base
        $mediaUrlBase = rtrim(Storage::disk('media')->url(''), '/');

        // Build response directly
        $data = $caves->map(function ($cave) use (
            $mediaBycave,
            $caveTags,
            $systems,
            $systemTags,
            $specialTags,
            $visitedSystemIds,
            $hasApprovedClub,
            $mediaUrlBase,
        ) {
            $hasDone = $visitedSystemIds->has($cave->cave_system_id);
            $system = $systems->get($cave->cave_system_id);

            // Tags
            $tags = [];
            if ($caveTags->has($cave->id)) {
                foreach ($caveTags->get($cave->id) as $t) {
                    $tags[] = ['id' => $t->id, 'tag' => $t->tag, 'category' => $t->category];
                }
            }

            $doneTag = $hasDone ? $specialTags->get('Previously Done') : $specialTags->get('Not Done Yet');
            if ($doneTag) {
                $tags[] = ['id' => $doneTag->id, 'tag' => $doneTag->tag, 'category' => $doneTag->category];
            }

            // System length tags
            $lengthTags = [];
            if ($system) {
                $length = $system->length;
                foreach ([['> 5km', 5000], ['> 1km', 1000], ['> 500m', 500], ['> 250m', 250]] as [$label, $min]) {
                    if ($length >= $min && ($st = $specialTags->get($label))) {
                        $lt = ['id' => $st->id, 'tag' => $st->tag, 'category' => $st->category];
                        $tags[] = $lt;
                        $lengthTags[] = $lt;
                    }
                }
                if ($length > 0 && $length < 250 && ($st = $specialTags->get('< 250m'))) {
                    $lt = ['id' => $st->id, 'tag' => $st->tag, 'category' => $st->category];
                    $tags[] = $lt;
                    $lengthTags[] = $lt;
                }
            }

            // System tags
            $sysTagArr = [];
            if ($system && $systemTags->has($system->id)) {
                foreach ($systemTags->get($system->id) as $t) {
                    $sysTagArr[] = ['id' => $t->id, 'tag' => $t->tag, 'category' => $t->category];
                }
            }

            // Media — return object with just the url property
            $media = $mediaBycave->get($cave->id);
            $mediaUrl = function ($type) use ($media, $mediaUrlBase) {
                if (!$media) {
                    return;
                }
                $m = $media->firstWhere('type', $type);
                if (!$m || !$m->filename) {
                    return;
                }

                return [
                    'url' => \App\Support\MediaUrl::url($m->filename, $mediaUrlBase),
                    'srcset' => \App\Support\MediaUrl::srcset($m->filename, $mediaUrlBase),
                ];
            };

            return [
                'id' => $cave->id,
                'slug' => $cave->slug,
                'name' => $cave->name,
                'hero_image' => $mediaUrl('hero'),
                'hero_video' => $mediaUrl('hero_video'),
                'entrance_image' => $mediaUrl('entrance'),
                'tags' => $tags,
                'location_name' => $cave->location_name,
                'location_country' => $cave->location_country,
                'location_lat' => $hasApprovedClub ? $cave->location_lat : null,
                'location_lng' => $hasApprovedClub ? $cave->location_lng : null,
                'system' => $system ? [
                    'id' => $system->id,
                    'name' => $system->name,
                    'length' => $system->length,
                    'vertical_range' => $system->vertical_range,
                    'tags' => array_merge($sysTagArr, $lengthTags),
                ] : null,
                'previously_done' => $hasDone,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function search(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        // Fetch the curated tag id once
        $curatedTagId = DB::table('tags')
            ->where('tag', 'Curated')
            ->where('category', 'curated')
            ->value('id');

        // Fetch the closed tag id once
        $closedTagId = DB::table('tags')
            ->where('tag', 'Closed')
            ->value('id');

        $caves = DB::table('caves')
            ->select([
                'caves.id',
                'caves.name',
                'caves.location_name',
                'caves.location_country',
                'caves.cave_system_id',
                DB::raw('CASE WHEN ct_curated.cave_id IS NOT NULL THEN 1 ELSE 0 END as is_curated'),
                DB::raw('CASE WHEN ct_closed.cave_id IS NOT NULL THEN 1 ELSE 0 END as is_closed'),
            ])
            ->tap(fn ($q) => \App\Support\CaveVisibility::publicOnly($q))
            ->leftJoin('cave_tag as ct_curated', function ($join) use ($curatedTagId) {
                $join->on('caves.id', '=', 'ct_curated.cave_id')
                    ->where('ct_curated.tag_id', '=', $curatedTagId);
            })
            ->leftJoin('cave_tag as ct_closed', function ($join) use ($closedTagId) {
                $join->on('caves.id', '=', 'ct_closed.cave_id')
                    ->where('ct_closed.tag_id', '=', $closedTagId);
            })
            ->orderByDesc('is_curated')
            ->orderBy('caves.name')
            ->get()
            ->map(fn ($cave) => [
                'id' => $cave->id,
                'name' => $cave->name,
                'location_name' => $cave->location_name,
                'location_country' => $cave->location_country,
                'cave_system_id' => $cave->cave_system_id,
                'is_curated' => (bool) $cave->is_curated,
                'is_closed' => (bool) $cave->is_closed,
            ]);

        return response()->json(['data' => $caves]);
    }

    public function store(StoreCaveRequest $request): CaveResource
    {
        $validData = $request->validated();
        if (empty($validData['slug'])) {
            $validData['slug'] = Str::slug($validData['name']);
        }

        $cave = Cave::create($validData);

        // Process tags
        if ($request->has('tags')) {
            $tags = collect($request->input('tags', []))->map(function ($tag) {
                return Tag::where([
                    'category' => $tag['category'],
                    'tag' => $tag['tag'],
                    'assignable' => true,
                ])->first()?->id;
            })->filter();
            $cave->tags()->sync($tags);
        }

        // Process hero image
        if ($request->has('hero_image')) {
            $this->processImageFieldOnCreate($request, $cave, 'hero');
        }

        // Process entrance image
        if ($request->has('entrance_image')) {
            $this->processImageFieldOnCreate($request, $cave, 'entrance');
        }

        // Process hero video
        if ($request->has('hero_video')) {
            $this->processVideoFieldOnCreate($request, $cave, 'hero_video');
        }

        return new CaveResource($cave->fresh(['media', 'heroImage', 'entranceImage', 'heroVideo']));
    }

    private function processImageFieldOnCreate(StoreCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type.'_image';
        $imageData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        if ($fileData) {
            $imageData['data'] = $fileData;
            $filePath = $this->imageProcessingService->processAndStoreImage($imageData, 'caves', $type);

            $media = $cave->media()->create([
                'type' => $type,
                'filename' => $filePath,
                'title' => $imageData['title'] ?? null,
                'photographer' => $imageData['photographer'] ?? null,
                'copyright' => $imageData['copyright'] ?? null,
            ]);

            // Generate responsive WebP variants (and preserve the source) so the
            // cave list serves small images on slow connections.
            \App\Jobs\ProcessImageCloudJob::dispatch($filePath, \App\Models\CaveMedia::class, $media->id);
        }
    }

    private function processVideoFieldOnCreate(StoreCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type;
        $videoData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        if ($fileData) {
            $videoData['data'] = $fileData;
            $filePath = $this->imageProcessingService->processAndStoreVideo($videoData, 'caves', $type);

            $cave->media()->create([
                'type' => $type,
                'filename' => $filePath,
                'title' => $videoData['title'] ?? null,
                'photographer' => $videoData['photographer'] ?? null,
                'copyright' => $videoData['copyright'] ?? null,
            ]);
        }
    }

    public function show($id)
    {
        $query = Cave::with([
            'system.catchment', 'system.caves', 'system.files', 'system.routes', 'system.annotation',
            'trips' => function ($q) {
                $q->visibleTo(auth()->user())->orderBy('start_time', 'desc')->limit(25)->with(['participants', 'media']);
            },
            'tags', 'collections', 'media', 'heroImage', 'entranceImage', 'heroVideo',
        ]);

        if (is_numeric($id)) {
            $cave = $query->where('id', $id)->first();
        }

        if (!isset($cave)) {
            $cave = $query->where('slug', $id)->firstOrFail();
        }

        // admin_only sites must not reveal their existence to unauthorised users.
        if ($cave->visibility === 'admin_only'
            && !app(\App\Policies\CavePolicy::class)->view(request()->user(), $cave)) {
            abort(404);
        }

        return new CaveResource($cave);
    }

    public function update(UpdateCaveRequest $request, Cave $cave): CaveResource
    {
        $data = $request->validated();
        $cave->update($data);

        // Update tags only when supplied — a partial update (e.g. name only)
        // must not silently wipe a cave's tags (which could orphan it from its
        // registry group and lock a scoped admin out of their own record).
        if ($request->has('tags')) {
            $tags = collect($request->input('tags', []))->map(function ($tag) {
                return Tag::where([
                    'category' => $tag['category'],
                    'tag' => $tag['tag'],
                    'assignable' => true,
                ])->first()?->id;
            })->filter();
            $cave->tags()->sync($tags);
        }

        // Process hero image
        $this->processImageField($request, $cave, 'hero');

        // Process entrance image
        $this->processImageField($request, $cave, 'entrance');

        // Process hero video
        $this->processVideoField($request, $cave, 'hero_video');

        return new CaveResource($cave->fresh(['media', 'heroImage', 'entranceImage', 'heroVideo']));
    }

    public function destroy(\Illuminate\Http\Request $request, Cave $cave): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user || !app(\App\Policies\CavePolicy::class)->delete($user, $cave)) {
            return response()->json(['error' => 'User is not authorised to perform that action'], 403);
        }

        $cave->delete();

        return response()->json(null, 204);
    }

    public function restore(\Illuminate\Http\Request $request, Cave $cave): CaveResource
    {
        $user = $request->user();
        // A restore is a management action — gate it like an update/delete.
        if (!$user || !app(\App\Policies\CavePolicy::class)->delete($user, $cave)) {
            abort(403, 'User is not authorised to perform that action');
        }

        $cave->restore();

        return new CaveResource($cave->fresh(['media', 'heroImage', 'entranceImage', 'heroVideo']));
    }

    private function processImageField(UpdateCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type.'_image';

        $hasField = $request->has($fieldName);
        $imageData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        $isNull = !$fileData && (empty($imageData) || (isset($imageData['data']) && $imageData['data'] === null));

        if ($hasField && !$isNull) {
            if ($fileData) {
                $imageData['data'] = $fileData;
                $filePath = $this->imageProcessingService->processAndStoreImage($imageData, 'caves', $type);

                $media = $cave->media()->updateOrCreate(
                    ['type' => $type],
                    [
                        'filename' => $filePath,
                        // Reset so the webhook records the new source as the original.
                        'original_filename' => null,
                        'title' => $imageData['title'] ?? null,
                        'photographer' => $imageData['photographer'] ?? null,
                        'copyright' => $imageData['copyright'] ?? null,
                    ]
                );

                // Generate responsive WebP variants (and preserve the source) so the
                // cave list serves small images on slow connections.
                \App\Jobs\ProcessImageCloudJob::dispatch($filePath, \App\Models\CaveMedia::class, $media->id);
            } elseif (is_array($imageData)) {
                // Metadata update only (if file already exists or is not being replaced)
                $cave->media()->where('type', $type)->update([
                    'title' => $imageData['title'] ?? null,
                    'photographer' => $imageData['photographer'] ?? null,
                    'copyright' => $imageData['copyright'] ?? null,
                ]);
            }
        } elseif ($hasField && $isNull) {
            $cave->media()->where('type', $type)->delete();
        }
    }

    private function processVideoField(UpdateCaveRequest $request, Cave $cave, string $type): void
    {
        $fieldName = $type;

        $hasField = $request->has($fieldName);
        $videoData = $request->input($fieldName, []);
        $fileData = $request->file($fieldName.'.data');

        $isNull = !$fileData && (empty($videoData) || (isset($videoData['data']) && $videoData['data'] === null));

        if ($hasField && !$isNull) {
            if ($fileData) {
                $videoData['data'] = $fileData;
                $filePath = $this->imageProcessingService->processAndStoreVideo($videoData, 'caves', $type);

                $cave->media()->updateOrCreate(
                    ['type' => $type],
                    [
                        'filename' => $filePath,
                        'title' => $videoData['title'] ?? null,
                        'photographer' => $videoData['photographer'] ?? null,
                        'copyright' => $videoData['copyright'] ?? null,
                    ]
                );
            } elseif (is_array($videoData)) {
                // Metadata update only
                $cave->media()->where('type', $type)->update([
                    'title' => $videoData['title'] ?? null,
                    'photographer' => $videoData['photographer'] ?? null,
                    'copyright' => $videoData['copyright'] ?? null,
                ]);
            }
        } elseif ($hasField && $isNull) {
            $cave->media()->where('type', $type)->delete();
        }
    }
}
