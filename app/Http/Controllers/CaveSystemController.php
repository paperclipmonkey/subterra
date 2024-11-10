<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveSystemRequest;
use App\Http\Requests\UpdateCaveSystemRequest;
use App\Http\Resources\CaveSystemResource;
use App\Models\Cave;
use App\Models\CaveSystem;

class CaveSystemController extends Controller
{
    public function index()
    {
        return CaveSystemResource::collection(CaveSystem::orderBy('name')->get());
    }

    public function store(StoreCaveSystemRequest $request)
    {
        CaveSystem::create($request->all())->save();
    }

    public function show(CaveSystem $caveSystem)
    {
        // return $caveSystem->toArray();
        return new CaveSystemResource($caveSystem);
    }

    public function update(UpdateCaveSystemRequest $request, CaveSystem $caveSystem)
    {
        $caveSystem->update($request->all());
        // Update tags
        $tags = collect($request->all()['tags'])->map(function ($tag) {
            return \App\Models\Tag::where([
                'category' => $tag['category'],
                'tag' => $tag['tag']
            ])->first()->id;
        });
        $caveSystem->tags()->sync($tags);
    }

    // public function destroy(Cave $cave)
    // {
    //     Cave::destroy($cave);
    // }
}
