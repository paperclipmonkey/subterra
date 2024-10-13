<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Requests\UpdateCaveRequest;
use App\Http\Resources\CaveResource;
use App\Models\Cave;

class CaveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return CaveResource::collection(Cave::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCaveRequest $request)
    {
        Cave::create($request->all())->save();
    }

    /**
     * Display the specified resource.
     */
    public function show(Cave $cave)
    {
        return new CaveResource($cave);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCaveRequest $request, Cave $cave)
    {
        $cave->update($request->all());
    }

    /**
     * Remove the specified resource from storage.
     */
    // public function destroy(Cave $cave)
    // {
    //     Cave::destroy($cave);
    // }
}
