<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Requests\UpdateCaveRequest;
use App\Http\Resources\CaveResource;
use App\Models\Cave;

class CaveController extends Controller
{
    public function index()
    {
        return CaveResource::collection(Cave::orderBy('name')->get());
    }

    public function store(StoreCaveRequest $request)
    {
        Cave::create($request->all())->save();
    }

    public function show(Cave $cave)
    {
        return new CaveResource($cave);
    }

    public function update(UpdateCaveRequest $request, Cave $cave)
    {
        $cave->update($request->all());
        // Update tags
        $tags = collect($request->all()['tags'])->map(function ($tag) {
            return \App\Models\Tag::where([
                'category' => $tag['category'],
                'tag' => $tag['tag']
            ])->first()->id;
        });
        $cave->tags()->sync($tags);
    }

    // public function destroy(Cave $cave)
    // {
    //     Cave::destroy($cave);
    // }
}
