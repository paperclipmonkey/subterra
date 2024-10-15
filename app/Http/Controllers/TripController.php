<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\ImageManagerStatic as DataUriImageDecoder;
use Intervention\Image\Interfaces\Webp\Encoder as WebpEncoder;

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
        $userId = auth()->id();
        $trips = Trip::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get();

        return TripResource::collection($trips);
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

        // Ensure the current user is added to the trip
        // $trip->participants()->attach($request->user());

        $media = $request->all()['media'];
        foreach ($media as $file) {
            $fileData = explode(',', $file['data']);
            $decodedData = base64_decode($fileData[1]);
            $image = Image::read($fileData[1], [
                \Intervention\Image\Decoders\DataUriImageDecoder::class,
                \Intervention\Image\Decoders\Base64ImageDecoder::class,
            ])->scaleDown(2048, 2048)->encode(new \Intervention\Image\Encoders\WebpEncoder(quality: 65));
            $filePath = 'media/' . \Illuminate\Support\Str::uuid() . '.webp';
            Storage::disk('media')->put($filePath, (string) $image);
            $trip->media()->create(['filename' => $filePath]);
        }

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
