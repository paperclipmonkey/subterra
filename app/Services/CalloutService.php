<?php

namespace App\Services;

use App\Events\CalloutCreated;
use App\Mail\CalloutCancelled;
use App\Mail\CalloutStarted;
use App\Models\Callout;
use App\Models\OnCallShift;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\SlackAlerts\Facades\SlackAlert;

class CalloutService
{
    public function __construct(
        private readonly GcpWatchdogService $watchdogService
    ) {
    }

    /**
     * Create a new callout.
     * Throws exception if no admin is on call for the requested time.
     */
    public function create(User $user, array $data): Callout
    {
        $calloutTime = Carbon::parse($data['callout_time']);

        if (!OnCallShift::isCovered($calloutTime)) {
            throw new Exception('Cannot create callout: No administrator is on-call at '.$calloutTime->toDateTimeString());
        }
        // Collect all checking phones
        $phonesToCheck = collect($data['participants'] ?? [])->pluck('phone')->filter();
        if ($user->phone) {
            $phonesToCheck->push($user->phone);
        }

        // Also fetch phones for any user_ids provided in participants if phone is missing?
        // For now, rely on provided phones or strictly enforce "One active callout per person"

        if ($phonesToCheck->isNotEmpty()) {
            $existingCallout = Callout::query()
                ->whereIn('status', ['active', 'triggered'])
                ->where(function ($query) use ($phonesToCheck) {
                    $query->whereHas('participants', function ($q) use ($phonesToCheck) {
                        $q->whereIn('phone', $phonesToCheck);
                    })
                    ->orWhereHas('user', function ($q) use ($phonesToCheck) {
                        $q->whereIn('phone', $phonesToCheck);
                    });
                })
                ->first();

            if ($existingCallout) {
                throw new Exception('One or more participants (or you) are already in an active callout. Please resolve the existing callout first.');
            }
        }

        return DB::transaction(function () use ($user, $data, $calloutTime) {
            $callout = Callout::create([
                'user_id' => $user->id,
                'trip_id' => $data['trip_id'] ?? null,
                'cave_id' => $data['cave_id'] ?? null,
                'exit_cave_id' => $data['exit_cave_id'] ?? null,
                'callout_time' => $calloutTime,
                'description' => $data['description'] ?? $data['trip_plan'] ?? 'Callout created via API',
                'trip_plan' => $data['trip_plan'] ?? null,
                'car_details' => $data['car_details'] ?? null,
                'car_registration' => $data['car_registration'] ?? null,
                'car_parking' => $data['car_parking'] ?? null,
                'location_data' => $data['location_data'] ?? null,
                'request_data' => $data['request_data'] ?? null,
                'team_details' => $data['team_details'] ?? null,
                'status' => 'active',
            ]);

            if (!empty($data['participants'])) {
                foreach ($data['participants'] as $p) {
                    $callout->participants()->create([
                        'user_id' => $p['user_id'] ?? null,
                        'name' => $p['name'],
                        'phone' => $p['phone'] ?? null,
                        'email' => $p['email'] ?? null,
                    ]);
                }
            }

            try {
                $this->watchdogService->register($callout);
            } catch (Exception $e) {
                Log::error('GCP Watchdog registration failed: '.$e->getMessage());
                // Don't fail the callout creation if watchdog registration fails
            }

            try {
                // Collect all emails
                $emails = collect($callout->refresh()->participants)
                    ->pluck('email')
                    ->filter();

                if ($user->email) {
                    $emails->push($user->email);
                }

                $emails->unique()->each(function ($email) use ($callout) {
                    Mail::to($email)->send(new CalloutStarted($callout));
                });
            } catch (Exception $e) {
                Log::error('Email Failure creating callout: '.$e->getMessage());
                // Don't rollback transaction for email failure
            }

            CalloutCreated::dispatch($callout);

            try {
                $location = $callout->cave ? $callout->cave->name : 'Custom Location';

                // Calculate participants correctly:
                // If creator is in the participants list, use the count.
                // If creator is NOT in the participants list (unlikely based on frontend but possible via API), add 1.
                $pCount = $callout->participants()->count();
                $creatorIsParticipant = $callout->participants()->where('user_id', $user->id)->exists();
                $totalParticipants = $creatorIsParticipant ? $pCount : $pCount + 1;

                SlackAlert::to('callouts-open')
                    ->message(":wave: New Callout: *{$location}* | Party of {$totalParticipants} | Return: {$callout->callout_time->format('H:i')}");
            } catch (\Exception $e) {
                // Ignore Slack failures
            }

            return $callout;
        });
    }

    /**
     * Cancel a callout (Mark as resolved/safe).
     */
    public function cancel(Callout $callout): ?Trip
    {
        $trip = $this->createTripFromCallout($callout);

        try {
            $this->watchdogService->cancel($callout);
        } catch (Exception $e) {
            Log::error('GCP Watchdog cancellation failed: '.$e->getMessage());
            // Continue with cancellation even if watchdog fails
        }

        try {
            // Ensure participants are loaded
            $callout->loadMissing('participants');

            // Collect all emails
            $emails = collect($callout->participants ?? [])
                ->pluck('email')
                ->filter();

            if ($callout->user && $callout->user->email) {
                $emails->push($callout->user->email);
            }

            $emails->unique()->each(function ($email) use ($callout) {
                Mail::to($email)->send(new CalloutCancelled($callout));
            });
        } catch (Exception $e) {
            Log::error('Email Failure cancelling callout: '.$e->getMessage());
        }
        if ($callout->incident()->exists()) {
            // DO NOT DELETE if rescue is underway.
            // Mark user as safe but leave incident for admin to close.
            $callout->update(['status' => 'cancelled']);

            // Add system note to incident
            $callout->incident->notes()->create([
                'user_id' => null, // System note
                'content' => 'USER MARKED THEMSELVES SAFE via App. Please verify and resolve incident.',
            ]);

            return $trip;
        }

        // 5. Mark as cancelled (instead of deleting)
        $callout->update(['status' => 'cancelled']);

        return $trip;
    }

    /**
     * Create a Trip record from a Callout.
     */
    private function createTripFromCallout(Callout $callout): Trip
    {
        $cave = $callout->cave;
        $systemId = $cave ? $cave->cave_system_id : null;

        if (!$systemId) {
            // If we don't have a system, we can't create a valid trip record
            // based on the current database constraints.
            return new Trip(); // Return empty model or handle differently
        }

        $trip = Trip::create([
            'name' => ($cave ? $cave->name : 'Custom Location').' Trip',
            'description' => $callout->trip_plan ?: $callout->description,
            'start_time' => $callout->created_at,
            'end_time' => now(), // Time of cancellation
            'cave_system_id' => $systemId,
            'entrance_cave_id' => $callout->cave_id,
            'exit_cave_id' => $callout->exit_cave_id ?: $callout->cave_id,
            'visibility' => 'private',
        ]);

        // Add creator as participant
        $participants = collect([$callout->user_id]);

        // Add other registered users from callout participants
        $registeredParticipants = $callout->participants()
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $participants = $participants->concat($registeredParticipants)->unique();

        $trip->participants()->sync($participants);

        return $trip;
    }

    /**
     * Trigger a callout (Mark as triggered and create Incident).
     * This happens when the time expires and user is not safe.
     */
    public function trigger(Callout $callout): void
    {
        DB::transaction(function () use ($callout) {
            $callout->update(['status' => 'triggered']);

            $callout->incident()->create([
                'status' => 'open',
            ]);
        });
    }
}
