<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$start = microtime(true);

$t1 = microtime(true);
$caves = \DB::table('caves')
    ->select(['id', 'slug', 'name', 'location_name', 'location_country', 'location_lat', 'location_lng', 'cave_system_id'])
    ->orderBy('name')
    ->get();
$caveIds = $caves->pluck('id');
$systemIds = $caves->pluck('cave_system_id')->unique()->filter();
$t2 = microtime(true);
echo 'Caves query: '.round(($t2 - $t1) * 1000)."ms ({$caves->count()} rows)\n";

$t3 = microtime(true);
$media = \DB::table('cave_media')
    ->select(['id', 'cave_id', 'type', 'filename', 'title', 'photographer', 'copyright'])
    ->whereIn('cave_id', $caveIds)
    ->whereIn('type', ['hero', 'entrance', 'hero_video'])
    ->get()->groupBy('cave_id');
$caveTags = \DB::table('cave_tag')
    ->join('tags', 'tags.id', '=', 'cave_tag.tag_id')
    ->select(['cave_tag.cave_id', 'tags.id', 'tags.tag', 'tags.category'])
    ->whereIn('cave_tag.cave_id', $caveIds)
    ->get()->groupBy('cave_id');
$systems = \DB::table('cave_systems')
    ->select(['id', 'name', 'catchment_id', 'length', 'vertical_range'])
    ->whereIn('id', $systemIds)
    ->get()->keyBy('id');
$systemTags = \DB::table('cave_system_tag')
    ->join('tags', 'tags.id', '=', 'cave_system_tag.tag_id')
    ->select(['cave_system_tag.cave_system_id', 'tags.id', 'tags.tag', 'tags.category'])
    ->whereIn('cave_system_tag.cave_system_id', $systemIds)
    ->get()->groupBy('cave_system_id');
$specialTags = \DB::table('tags')
    ->select(['id', 'tag', 'category'])
    ->whereIn('tag', ['Previously Done', 'Not Done Yet', '> 5km', '> 1km', '> 500m', '> 250m'])
    ->get()->keyBy('tag');
$t4 = microtime(true);
echo 'Related queries: '.round(($t4 - $t3) * 1000)."ms\n";

$t5 = microtime(true);
$mediaUrlBase = rtrim(\Illuminate\Support\Facades\Storage::disk('media')->url(''), '/');
$visitedSystemIds = collect()->flip();
$hasApprovedClub = false;

$data = $caves->map(function ($cave) use ($media, $caveTags, $systems, $systemTags, $specialTags, $visitedSystemIds, $hasApprovedClub, $mediaUrlBase) {
    $hasDone = $visitedSystemIds->has($cave->cave_system_id);
    $system = $systems->get($cave->cave_system_id);
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
    $lengthTags = [];
    if ($system) {
        foreach ([['> 5km', 5000], ['> 1km', 1000], ['> 500m', 500], ['> 250m', 250]] as [$label, $min]) {
            if ($system->length >= $min && ($st = $specialTags->get($label))) {
                $lt = ['id' => $st->id, 'tag' => $st->tag, 'category' => $st->category];
                $tags[] = $lt;
                $lengthTags[] = $lt;
            }
        }
    }
    $sysTagArr = [];
    if ($system && $systemTags->has($system->id)) {
        foreach ($systemTags->get($system->id) as $t) {
            $sysTagArr[] = ['id' => $t->id, 'tag' => $t->tag, 'category' => $t->category];
        }
    }
    $m = $media->get($cave->id);
    $fmt = function ($type) use ($m, $mediaUrlBase) {
        if (!$m) {
            return;
        }
        $r = $m->firstWhere('type', $type);
        if (!$r) {
            return;
        }

        return [
            'id' => $r->id, 'type' => $r->type, 'filename' => $r->filename,
            'url' => $r->filename ? (str_starts_with($r->filename, 'http') ? $r->filename : $mediaUrlBase.'/'.$r->filename) : null,
            'title' => $r->title, 'photographer' => $r->photographer, 'copyright' => $r->copyright,
        ];
    };

    return [
        'id' => $cave->id, 'slug' => $cave->slug, 'name' => $cave->name,
        'hero_image' => $fmt('hero'), 'hero_video' => $fmt('hero_video'), 'entrance_image' => $fmt('entrance'),
        'tags' => $tags, 'location_name' => $cave->location_name, 'location_country' => $cave->location_country,
        'location_lat' => $hasApprovedClub ? $cave->location_lat : null,
        'location_lng' => $hasApprovedClub ? $cave->location_lng : null,
        'system' => $system ? [
            'id' => $system->id, 'name' => $system->name, 'catchment_id' => $system->catchment_id,
            'length' => $system->length, 'vertical_range' => $system->vertical_range,
            'tags' => array_merge($sysTagArr, $lengthTags),
        ] : null,
        'previously_done' => $hasDone,
    ];
});
$t6 = microtime(true);
echo 'Array build: '.round(($t6 - $t5) * 1000)."ms\n";

$t7 = microtime(true);
$json = json_encode(['data' => $data]);
$t8 = microtime(true);
echo 'JSON encode: '.round(($t8 - $t7) * 1000)."ms\n";
echo 'Response size: '.round(strlen($json) / 1024)."KB\n";
echo 'TOTAL: '.round(($t8 - $start) * 1000)."ms\n";
