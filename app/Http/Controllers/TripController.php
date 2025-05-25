<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\DeleteTripRequest;
use App\Http\Resources\TripResource;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Trip;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use App\Models\User;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;

class TripController extends Controller
{
    public function index(): ResourceCollection
    {
        return TripResource::collection(Trip::all());
    }

    public function indexMe(): ResourceCollection
    {
        $userId = auth()->id();
        $trips = Trip::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with('entrance')->orderBy('start_time', 'desc')->get(); // Eager load entrance and order by date

        return TripResource::collection($trips);
    }

    public function downloadMyTripsCsv(): StreamedResponse
    {
        $userId = auth()->id();
        $filename = "my_trips.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($userId) {
            $handle = fopen('php://output', 'w');

            // Add CSV Header
            fputcsv($handle, [
                'Trip ID',
                'Trip Name',
                'Start Time',
                'End Time',
                'Cave Name',
                'Entrance Name',
                'Description',
                'Participants'
                // Add more columns if needed
            ]);

            // Using chunking for potentially large datasets
            Trip::whereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with('entrance') // Eager load relationships
            // ->orderBy('date', 'desc') // Revert orderBy back to 'date'
            ->chunk(200, function ($trips) use ($handle) {
                foreach ($trips as $trip) {
                    fputcsv($handle, [
                        $trip->id,
                        $trip->name,
                        $trip->start_time?->format('Y-m-d') ?? 'N/A', // Format date as needed
                        $trip->end_time?->format('Y-m-d') ?? 'N/A', // Format date as needed
                        $trip->entrance?->cave?->name ?? 'N/A', // Safely access nested relationship
                        $trip->entrance?->name ?? 'N/A', // Safely access relationship
                        $trip->description,
                        implode(', ', $trip->participants->pluck('name')->toArray())
                        // Add corresponding data for more columns
                    ]);
                }
            });

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function store(StoreTripRequest $request): TripResource
    {
        $trip = Trip::create($request->all());
        $trip->save();

        // Add the participants to the trip
        $participants = $request->input('participants');
        $participantIds = array_map(function ($id) {
            return User::withoutGlobalScopes()->where('id', $id)->first()->id;
        }, $participants);
    
        // Sync participants with the trip
        $trip->participants()->sync($participantIds);

        // Fire TripParticipantTagged event for each participant including the creator
        $creator = User::withoutGlobalScopes()->find(auth()->id());
        foreach ($participantIds as $participantId) {
            $participant = User::withoutGlobalScopes()->find($participantId);
            if ($participant) {
                event(new \App\Events\TripParticipantTagged($trip, $participant, $creator));
            }
        }

        $media = $request->input('media', []);
        $this->storeMedia($media, $trip);

        // Dispatch event instead of calling SlackAlert directly
        event(new \App\Events\TripCreated($trip, $creator));

        return new TripResource($trip);
    }

    private function storeMedia(array $media, $trip): void
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

    public function show(Trip $trip): TripResource
    {
        return new TripResource($trip);
    }

    public function update(UpdateTripRequest $request, Trip $trip)
    {
        $existingMedia = $request->input('existing_media') ?? [];

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
        $participantIds = array_map(function ($id) {
            return User::withoutGlobalScopes()->where('id', $id)->first()->id;
        }, $participants);
    
        // Sync participants with the trip
        $trip->participants()->sync($participantIds);
        
        $media = $request->all()['media'];
        $this->storeMedia($media, $trip);
    }

    public function destroy(DeleteTripRequest $request, Trip $trip): JsonResponse
    {
        $trip->delete();
        return response()->json([
            'message'=> 'Trip deleted successfully'
        ]);
    }
}
