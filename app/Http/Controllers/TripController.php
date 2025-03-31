<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\DeleteTripRequest;
use App\Http\Resources\TripResource;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\SlackAlerts\Facades\SlackAlert;
use App\Models\User;


class TripController extends Controller
{
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

    public function store(StoreTripRequest $request)
    {
        $trip = Trip::create($request->all());
        $trip->save();

        // Add the participants to the trip
        $participants = $request->all()['participants'];
        $participantIds = array_map(function ($email) {
            return User::withoutGlobalScopes()->where('email', $email)->first()->id;
        }, $participants);
    
        // Sync participants with the trip
        $trip->participants()->sync($participantIds);

        $media = $request->all()['media'] ?? [];
        $this->storeMedia($media, $trip);

        SlackAlert::to('trips')->message("A new trip has been created: <https://subterra.world/trip/{$trip->id}|{$trip->name}> to {$trip->entrance->name} by {$request->user()->name}");

        return new TripResource($trip);
    }

    private function storeMedia(array $media, $trip)
    {
        foreach ($media as $file) {
            $fileData = explode(',', $file['data']);
            $image = Image::read($fileData[1], [
                \Intervention\Image\Decoders\DataUriImageDecoder::class,
                \Intervention\Image\Decoders\Base64ImageDecoder::class,
            ])->scaleDown(2048, 2048)->encode(new \Intervention\Image\Encoders\WebpEncoder(quality: 65));
            $filePath = 'trip/' . \Illuminate\Support\Str::uuid() . '.webp';
            Storage::disk('media')->put($filePath, (string) $image);
            $trip->media()->create(['filename' => $filePath]);
        }
    }

    public function show(Trip $trip)
    {
        return new TripResource($trip);
    }

    public function update(UpdateTripRequest $request, Trip $trip)
    {
        $existingMedia = $request->input('existing_media');

        if(collect($existingMedia)->count() == 0) {
            $trip->media()->delete();
        }

        foreach ($existingMedia as $file) {
            $existingMediaIds = array_column($existingMedia, 'id');
            $trip->media()->whereNotIn('id', $existingMediaIds)->delete();
        }
        $trip->update(attributes: $request->all());

        // Add the participants to the trip
        $participants = $request->all()['participants'];
        $participantIds = array_map(function ($email) {
            return User::withoutGlobalScopes()->where('email', $email)->first()->id;
        }, $participants);
    
        // Sync participants with the trip
        $trip->participants()->sync($participantIds);
        
        $media = $request->all()['media'];
        $this->storeMedia($media, $trip);
    }

    public function destroy(DeleteTripRequest $request, Trip $trip)
    {
        $trip->delete();
        return response()->json([
            'message'=> 'Trip deleted successfully'
        ]);
    }
}
