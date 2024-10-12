<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCaveRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;

class TripController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Trip::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTripRequest $request)
    {
        Trip::create($request->all())->save();
    }

    /**
     * Display the specified resource.
     */
    public function show(Trip $trip)
    {
        return new TripResource($trip);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTripRequest $request, Trip $trip)
    {
        $trip->update($request->all());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Trip $cave)
    {
        Trip::destroy($cave);
    }
}
