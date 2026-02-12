<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\TripCreated;
use App\Events\TripParticipantTagged;
use App\Http\Requests\DeleteTripRequest;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use App\Services\ImageProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {
    }

    public function index(): ResourceCollection
    {
        $user = auth()->user();
        $query = Trip::visibleTo($user);

        // Only filter by user_id if explicitly provided
        $query->when(request()->input('user_id'), function ($q, $userId) {
            $q->whereHas('participants', function ($pq) use ($userId) {
                $pq->where('users.id', $userId);
            });
        });

        $trips = $query->with(['participants.clubs', 'entrance', 'media'])->orderBy('start_time', 'desc')->get();

        return TripResource::collection($trips);
    }

    public function indexMe(): ResourceCollection
    {
        $user = auth()->user();
        $userId = $user->id;
        $trips = Trip::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with('entrance')->visibleTo($user)->orderBy('start_time', 'desc')->get();

        return TripResource::collection($trips);
    }

    public function downloadMyTripsCsv(): StreamedResponse
    {
        $user = auth()->user();
        $userId = $user->id;
        $filename = 'my_trips.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($user, $userId) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Trip ID',
                'Trip Name',
                'Start Time',
                'End Time',
                'Cave Name',
                'Entrance Name',
                'Description',
                'Participants',
            ]);

            Trip::whereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->visibleTo($user)
            ->with('entrance')
            ->chunk(200, function ($trips) use ($handle) {
                foreach ($trips as $trip) {
                    fputcsv($handle, [
                        $trip->short_id,
                        $trip->name,
                        $trip->start_time?->format('Y-m-d') ?? 'N/A',
                        $trip->end_time?->format('Y-m-d') ?? 'N/A',
                        $trip->entrance?->cave?->name ?? 'N/A',
                        $trip->entrance?->name ?? 'N/A',
                        $trip->description,
                        implode(', ', $trip->participants->pluck('name')->toArray()),
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

        if (!isset($tripData['visibility'])) {
            $tripData['visibility'] = 'public';
        }

        $this->validateClosedAccess($tripData['entrance_cave_id'], $tripData['visibility']);

        $trip = Trip::create($tripData);
        $trip->save();

        $participants = $request->input('participants', []);
        $participantIds = array_map(function ($id) {
            return User::withoutGlobalScopes()->where('id', $id)->first()->id;
        }, $participants);

        $trip->participants()->sync($participantIds);

        // Fire TripParticipantTagged event for each participant including the creator
        $creator = User::withoutGlobalScopes()->find(auth()->id());
        foreach ($participantIds as $participantId) {
            $participant = User::withoutGlobalScopes()->find($participantId);
            if ($participant) {
                event(new TripParticipantTagged($trip, $participant, $creator));
            }
        }

        $media = $request->input('media', []);
        $this->storeMedia($media, $trip);

        // Dispatch event instead of calling SlackAlert directly
        event(new TripCreated($trip, $creator));

        return new TripResource($trip);
    }

    private function storeMedia(array $media, Trip $trip): void
    {
        foreach ($media as $file) {
            $filePath = $this->imageProcessingService->processAndStoreImage($file, 'trip');
            $mediaData = [
                'filename' => $filePath,
                'title' => $file['title'] ?? null,
                'taken_at' => $file['taken_at'] ?? null,
                'photographer' => $file['photographer'] ?? null,
                'copyright' => $file['copyright'] ?? null,
            ];

            $trip->media()->create($mediaData);
        }
    }

    public function show(Trip $trip): TripResource
    {
        $user = auth()->user();
        $visibleTrips = Trip::visibleTo($user)->where('id', $trip->id);

        if (!$visibleTrips->exists()) {
            abort(404, 'Trip not found');
        }

        $trip->load(['system', 'entrance', 'exit', 'participants', 'media']);

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

        // Validate Closed Access
        $data = $request->validated();
        $entranceCaveId = $data['entrance_cave_id'] ?? $trip->entrance_cave_id;
        $visibility = $data['visibility'] ?? $trip->visibility;
        $this->validateClosedAccess($entranceCaveId, $visibility);

        $trip->update($data);

        $participants = $request->input('participants', []);
        $participantIds = array_map(function ($id) {
            return User::withoutGlobalScopes()->where('id', $id)->first()->id;
        }, $participants);

        $trip->participants()->sync($participantIds);

        $media = $request->input('media', []);
        $this->storeMedia($media, $trip);

        return new TripResource($trip);
    }

    public function destroy(DeleteTripRequest $request, Trip $trip): JsonResponse
    {
        $trip->delete();

        return response()->json([
            'message' => 'Trip deleted successfully',
        ]);
    }

    private function validateClosedAccess($caveId, $visibility): void
    {
        if ($visibility === 'public') {
            $cave = \App\Models\Cave::with('tags', 'system.tags')->find($caveId);
            if ($cave) {
                $isClosed = $cave->tags->contains('tag', 'Closed') ||
                           ($cave->system && $cave->system->tags->contains('tag', 'Closed'));

                if ($isClosed) {
                    throw ValidationException::withMessages([
                        'visibility' => ['Closed caves cannot have public trip reports.'],
                    ]);
                }
            }
        }
    }
}
