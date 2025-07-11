<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTripRequest;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use App\Services\ImageProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {}

    public function index(): ResourceCollection
    {
        $user = auth()->user();
        $trips = Trip::visibleTo($user)->get();
        return TripResource::collection($trips);
    }

    public function indexMe(): ResourceCollection
    {
        $user = auth()->user();
        $userId = $user->id;
        $trips = Trip::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with('entrance')->visibleTo($user)->with('entrance')->orderBy('start_time', 'desc')->get();

        return TripResource::collection($trips);
    }

    public function downloadMyTripsCsv(): StreamedResponse
    {
        $user = auth()->user();
        $userId = $user->id;
        $filename = "my_trips.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($user, $userId) {
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
            ->visibleTo($user)
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
        $tripData = $request->all();
        
        // Set default visibility to 'public' if not provided
        if (!isset($tripData['visibility'])) {
            $tripData['visibility'] = 'public';
        }
        
        $trip = Trip::create($tripData);
        $trip->save();

        // Add the participants to the trip
        $participants = $request->input('participants', []);
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

    private function storeMedia(array $media, Trip $trip): void
    {
        foreach ($media as $file) {
            $filePath = $this->imageProcessingService->processAndStoreImage($file, 'trip');
            $trip->media()->create(['filename' => $filePath]);
        }
    }

    public function show(Trip $trip): TripResource
    {
        $user = auth()->user();
        
        // Check if the trip is visible to the current user
        $visibleTrips = Trip::visibleTo($user)->where('id', $trip->id);
        
        if (!$visibleTrips->exists()) {
            abort(404, 'Trip not found');
        }
        
        return new TripResource($trip);
    }

    public function update(UpdateTripRequest $request, Trip $trip): TripResource
    {
        $existingMedia = $request->input('existing_media', []);

        if (count($existingMedia) === 0) {
            $trip->media()->delete();
        } else {
            $existingMediaIds = array_column($existingMedia, 'id');
            $trip->media()->whereNotIn('id', $existingMediaIds)->delete();
        }

        $trip->update($request->validated());

        // Add the participants to the trip
        $participants = $request->input('participants', []);
        $participantIds = array_map(function ($id) {
            return User::withoutGlobalScopes()->where('id', $id)->first()->id;
        }, $participants);

        // Sync participants with the trip
        $trip->participants()->sync($participantIds);

        $media = $request->input('media', []);
        $this->storeMedia($media, $trip);

        return new TripResource($trip);
    }

    public function destroy(DeleteTripRequest $request, Trip $trip): JsonResponse
    {
        $trip->delete();
        return response()->json([
            'message'=> 'Trip deleted successfully'
        ]);
    }
}
