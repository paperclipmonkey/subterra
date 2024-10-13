<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;

class TripController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return TripResource::collection(Trip::all());
    }

    public function indexMe()
    {
        return Trip::all()->where('user_id', auth()->id());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTripRequest $request)
    {
        $trip = Trip::create($request->all());
        $trip->save();
        // Add the participant to the trip
        $participants = $request->all()['participants'];
        $trip->participants()->attach($participants);

        return new TripResource($trip);
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
    public function destroy(Trip $trip)
    {
        $trip->delete();
        return response()->json([
            'message'=> 'Trip deleted successfully'
        ]);
    }
}
