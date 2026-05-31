<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Events\TripCreated;
use App\Events\TripParticipantTagged;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateTripReportTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_trip_report',
                'description' => 'Create a trip report on behalf of the current user. '
                    .'Only call this when you have confirmed all required fields with the user: cave system, entrance cave, date, trip name, and description. '
                    .'The current user is always added as a participant automatically. '
                    .'If companions are not found in Subterra, include their names in additional_participants — they will be appended to the description. '
                    .'Returns the created trip short_id and an edit URL so the user can add photos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cave_system_slug' => [
                            'type' => 'string',
                            'description' => 'Slug of the cave system (from search_caves results). e.g. "gaping-gill"',
                        ],
                        'entrance_cave_slug' => [
                            'type' => 'string',
                            'description' => 'Slug of the entrance cave (from get_cave_details or search_caves). e.g. "main-shaft"',
                        ],
                        'exit_cave_slug' => [
                            'type' => 'string',
                            'description' => 'Slug of the exit cave for through-trips. Omit if same as entrance.',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Short title for the trip (max 255 characters). e.g. "Yorkshire weekend — Gaping Gill"',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Full trip report markdown text. Should include what was done, conditions, highlights.',
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Trip date in YYYY-MM-DD format.',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Entry time in HH:MM (24h) format. Optional.',
                        ],
                        'duration_minutes' => [
                            'type' => 'integer',
                            'description' => 'Trip duration in minutes. Optional. Used to calculate end_time from start_time.',
                        ],
                        'visibility' => [
                            'type' => 'string',
                            'enum' => ['public', 'private', 'club'],
                            'description' => 'Who can see the trip. Default: public.',
                        ],
                        'participant_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'User IDs of confirmed Subterra users on the trip (from search_users). Do NOT include the current user — they are added automatically.',
                        ],
                        'additional_participants' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of people on the trip who could not be found in Subterra. These will be noted in the trip description.',
                        ],
                    ],
                    'required' => ['cave_system_slug', 'entrance_cave_slug', 'name', 'description', 'date'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        // --- Resolve cave system ---
        $systemSlug = (string) ($arguments['cave_system_slug'] ?? '');
        $caveSystem = CaveSystem::where('slug', $systemSlug)->first();
        if (!$caveSystem) {
            return ['error' => "Cave system '{$systemSlug}' not found. Use search_caves to find the correct slug."];
        }

        // --- Resolve entrance cave ---
        $entranceSlug = (string) ($arguments['entrance_cave_slug'] ?? '');
        $entranceCave = Cave::where('slug', $entranceSlug)->first();
        if (!$entranceCave) {
            return ['error' => "Entrance cave '{$entranceSlug}' not found. Use get_cave_details to find the correct slug."];
        }

        // --- Resolve optional exit cave ---
        $exitCave = null;
        if (!empty($arguments['exit_cave_slug'])) {
            $exitCave = Cave::where('slug', (string) $arguments['exit_cave_slug'])->first();
            if (!$exitCave) {
                return ['error' => "Exit cave '{$arguments['exit_cave_slug']}' not found."];
            }
        }

        // --- Parse date and times ---
        $dateStr = (string) ($arguments['date'] ?? '');
        try {
            $date = Carbon::parse($dateStr);
        } catch (\Throwable) {
            return ['error' => "Invalid date format '{$dateStr}'. Use YYYY-MM-DD."];
        }

        $startTime = null;
        $endTime = null;

        if (!empty($arguments['start_time'])) {
            try {
                $startTime = Carbon::parse($dateStr.' '.$arguments['start_time'])->utc();
            } catch (\Throwable) {
                $startTime = $date->copy()->startOfDay()->utc();
            }
        } else {
            // Default to noon on the trip date if no time provided
            $startTime = $date->copy()->setTime(12, 0)->utc();
        }

        if (!empty($arguments['duration_minutes']) && $startTime) {
            $endTime = $startTime->copy()->addMinutes((int) $arguments['duration_minutes']);
        }

        // --- Handle additional participants not in the system ---
        $description = (string) ($arguments['description'] ?? '');
        $additionalParticipants = array_filter(
            array_map('trim', (array) ($arguments['additional_participants'] ?? [])),
            fn ($n) => $n !== ''
        );
        if (!empty($additionalParticipants)) {
            $names = implode(', ', $additionalParticipants);
            $note = "\n\n---\n*Also on the trip (not on Subterra): {$names}*";
            $description .= $note;
        }

        // --- Validate participant IDs exist ---
        $requestedIds = array_filter(
            array_map('trim', (array) ($arguments['participant_ids'] ?? [])),
            fn ($id) => $id !== ''
        );
        $validParticipantIds = [];
        if (!empty($requestedIds)) {
            $validParticipantIds = User::withoutGlobalScopes()
                ->whereIn('id', $requestedIds)
                ->pluck('id')
                ->toArray();

            $invalidCount = count($requestedIds) - count($validParticipantIds);
            if ($invalidCount > 0) {
                Log::warning('CreateTripReportTool: some participant IDs not found', [
                    'requested' => $requestedIds,
                    'valid' => $validParticipantIds,
                ]);
            }
        }

        // Always include the current user
        $allParticipantIds = array_unique(array_merge([$user->id], $validParticipantIds));

        // --- Create the trip ---
        $visibility = in_array($arguments['visibility'] ?? '', ['public', 'private', 'club'], true)
            ? $arguments['visibility']
            : 'public';

        $tripName = mb_substr(trim((string) ($arguments['name'] ?? '')), 0, 255);
        if ($tripName === '') {
            return ['error' => 'Trip name is required.'];
        }

        DB::beginTransaction();
        try {
            $trip = Trip::create([
                'name' => $tripName,
                'description' => $description,
                'cave_system_id' => $caveSystem->id,
                'entrance_cave_id' => $entranceCave->id,
                'exit_cave_id' => $exitCave?->id ?? $entranceCave->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'visibility' => $visibility,
            ]);

            $trip->participants()->sync($allParticipantIds);

            // Fire participant tagged events (for notifications)
            $participantModels = User::withoutGlobalScopes()->whereIn('id', $allParticipantIds)->get();
            foreach ($participantModels as $participant) {
                event(new TripParticipantTagged($trip, $participant, $user));
            }

            event(new TripCreated($trip, $user));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CreateTripReportTool: failed to create trip', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to create the trip report. Please try again.'];
        }

        $trip->refresh();

        return [
            'success' => true,
            'trip_id' => $trip->short_id,
            'trip_url' => "/trips/{$trip->short_id}",
            'edit_url' => "/trips/{$trip->short_id}/edit",
            'name' => $trip->name,
            'cave_system' => $caveSystem->name,
            'entrance' => $entranceCave->name,
            'date' => $date->format('Y-m-d'),
            'participants_added' => count($allParticipantIds),
            'additional_participants_noted' => !empty($additionalParticipants) ? $additionalParticipants : null,
            'message' => 'Trip created successfully. The user can add photos by visiting the edit URL.',
        ];
    }
}
