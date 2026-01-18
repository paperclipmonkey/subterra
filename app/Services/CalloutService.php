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
                'expected_exit_time' => isset($data['expected_exit_time']) ? Carbon::parse($data['expected_exit_time']) : null,
                'description' => $data['description'] ?? 'Callout created via API', 
                'trip_plan' => $data['trip_plan'] ?? null,
                'car_details' => $data['car_details'] ?? null,
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
                        if (!$phone && isset($p['user_id'])) {
                             // Theoretically could fetch user phone here if missing, but should be passed in data
                        }
                        
                        if ($phone) {
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

            return $callout;
        });
    }

    /**
     * Cancel a callout (Mark as resolved/safe).
     */
    public function cancel(Callout $callout): void
    {
        // 1. Remove from AWS (Best effort or sync?)
        // $this->awsService->cancelWatchdog($callout);

        // 2. Send Cancellation Emails
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

        // 3. Check for Active Incident
        if ($callout->incident()->exists()) {
            // DO NOT DELETE if rescue is underway. 
            // Mark user as safe but leave incident for admin to close.
            $callout->update(['status' => 'cancelled']);
            
            // Add system note to incident
            $callout->incident->notes()->create([
                'user_id' => null, // System note
                'content' => 'USER MARKED THEMSELVES SAFE via App. Please verify and resolve incident.'
            ]);
            
            return;
        }

        // 4. Delete from DB (Strict retention: Cancelled = Deleted)
        // Only if no incident was ever created (i.e. user is safe before panic time)
        $callout->delete();
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
