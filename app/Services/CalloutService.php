<?php

namespace App\Services;

use App\Models\Callout;
use App\Models\OnCallShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class CalloutService
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Create a new callout.
     * Throws exception if no admin is on call for the requested time.
     */
    public function create(User $user, array $data): Callout
    {
        $calloutTime = Carbon::parse($data['callout_time']);

        // 1. Validate On-Call Coverage
        if (!OnCallShift::isCovered($calloutTime)) {
            throw new Exception("Cannot create callout: No administrator is on-call at " . $calloutTime->toDateTimeString());
        }

        return DB::transaction(function () use ($user, $data, $calloutTime) {
            // 2. Create Callout Record
            $callout = Callout::create([
                'user_id' => $user->id,
                'trip_id' => $data['trip_id'] ?? null,
                'cave_id' => $data['cave_id'] ?? null,
                'exit_cave_id' => $data['exit_cave_id'] ?? null,
                'callout_time' => $calloutTime,
                'description' => $data['description'] ?? 'Callout created via API', 
                'trip_plan' => $data['trip_plan'] ?? null,
                'car_details' => $data['car_details'] ?? null,
                'car_registration' => $data['car_registration'] ?? null,
                'car_parking' => $data['car_parking'] ?? null,
                'location_data' => $data['location_data'] ?? null,
                'request_data' => $data['request_data'] ?? null,
                'team_details' => $data['team_details'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'status' => 'active',
            ]);

            // 3. Add Participants
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
            
            // 4. TODO: Synchronous Call to AWS Watchdog (Phase 4 Integration)
            // $this->awsService->registerWatchdog($callout);

            // 5. Send Confirmations (Phase 3 Notifications)
            try {
                // Notify User
                if ($user->phone) {
                    $this->smsService->sendMessage(
                        $user->phone, 
                        "Subterra: Callout ACTIVE for {$calloutTime->format('H:i')}. Reply SAFE to cancel."
                    );
                }

                // Notify Participants
                if (!empty($data['participants'])) {
                    foreach ($data['participants'] as $p) {
                        // Ensure we have a phone number either from user or input
                        $phone = $p['phone'] ?? null;
                        
                        // Avoid sending a "participant" SMS to the creator if they already got the "creator" SMS above
                        if ($phone && $phone !== $user->phone) {
                            $this->smsService->sendMessage(
                                $phone,
                                "Subterra: You are listed on a callout for {$user->name}. Returns: {$calloutTime->format('H:i')}."
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                // Log the real error
                \Illuminate\Support\Facades\Log::error("SMS Failure creating callout: " . $e->getMessage());
                
                // Throw user-friendly error (Transaction will rollback)
                throw new Exception("Something went wrong and we were unable to save your callout with Subterra. Please use alternative arrangements.");
            }

            // 6. Send Email Notifications (Fire and Forget or Queued ideally)
            try {
                // Collect all emails
                $emails = collect($data['participants'] ?? [])
                    ->pluck('email')
                    ->filter();
                
                if ($user->email) {
                    $emails->push($user->email);
                }

                $emails->unique()->each(function ($email) use ($callout) {
                    \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\CalloutStarted($callout));
                });

            } catch (Exception $e) {
                 \Illuminate\Support\Facades\Log::error("Email Failure creating callout: " . $e->getMessage());
                 // Don't rollback transaction for email failure
            }
            
            // 7. Dispatch Created Event (Slack, etc)
            \App\Events\CalloutCreated::dispatch($callout);

            // 8. Slack Notification
            try {
                $location = $callout->cave ? $callout->cave->name : 'Custom Location';
                
                // Calculate participants correctly:
                // If creator is in the participants list, use the count.
                // If creator is NOT in the participants list (unlikely based on frontend but possible via API), add 1.
                $pCount = $callout->participants()->count();
                $creatorIsParticipant = $callout->participants()->where('user_id', $user->id)->exists();
                $totalParticipants = $creatorIsParticipant ? $pCount : $pCount + 1;
                
                \Spatie\SlackAlerts\Facades\SlackAlert::to('callouts-open')
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
    public function cancel(Callout $callout): ?\App\Models\Trip
    {
        // 1. Create a Trip record from the callout
        // We do this before deleting the callout to preserve its data/participants
        $trip = $this->createTripFromCallout($callout);

        // 2. Remove from AWS (Best effort or sync?)
        // $this->awsService->cancelWatchdog($callout);

        // 3. Send Cancellation Emails
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
                \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\CalloutCancelled($callout));
            });

        } catch (Exception $e) {
                \Illuminate\Support\Facades\Log::error("Email Failure cancelling callout: " . $e->getMessage());
        }

        // 4. Check for Active Incident
        if ($callout->incident()->exists()) {
            // DO NOT DELETE if rescue is underway. 
            // Mark user as safe but leave incident for admin to close.
            $callout->update(['status' => 'cancelled']);
            
            // Add system note to incident
            $callout->incident->notes()->create([
                'user_id' => null, // System note
                'content' => 'USER MARKED THEMSELVES SAFE via App. Please verify and resolve incident.'
            ]);
            
            return $trip;
        }

        // 5. Delete from DB (Strict retention: Cancelled = Deleted)
        // Only if no incident was ever created (i.e. user is safe before panic time)
        $callout->delete();

        return $trip;
    }

    /**
     * Create a Trip record from a Callout.
     */
    private function createTripFromCallout(Callout $callout): \App\Models\Trip
    {
        $cave = $callout->cave;
        $systemId = $cave ? $cave->cave_system_id : null;

        // If for some reason we have a callout without a cave, we still need a system ID.
        // Based on frontend this shouldn't happen, but we should be safe.
        if (!$systemId && $cave) {
             $systemId = $cave->cave_system_id;
        }

        $trip = \App\Models\Trip::create([
            'name' => ($cave ? $cave->name : 'Custom Location') . ' Trip',
            'description' => $callout->description,
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
            // 1. Update Callout Status
            $callout->update(['status' => 'triggered']);

            // 2. Create Incident
            $callout->incident()->create([
                'status' => 'open',
            ]);
        });
    }
}
