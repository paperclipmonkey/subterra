<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CalloutCancelled;
use App\Events\CalloutCreated;
use App\Mail\CalloutCancelled as CalloutCancelledMail;
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
    /**
     * Statuses in which a callout is already finished — cancelling again must be a no-op.
     * 'resolved' is set by Incident::resolve(), 'cancelled' by this service.
     *
     * @var array<int, string>
     */
    private const FINISHED_STATUSES = ['cancelled', 'resolved'];

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
        // A callout must never be created in an environment that can't actually raise
        // the alarm. Refuse if essential configuration (Twilio credentials, the sender
        // number, the backup number, …) is missing, rather than create a callout that
        // can never alert anyone. (Disabled in tests / via CALLOUT_ENFORCE_CONFIG.)
        if (config('callouts.enforce_config')) {
            $missing = $this->missingEssentialConfig();

            if (!empty($missing)) {
                Log::critical('Callout creation blocked: essential configuration is missing.', [
                    'missing' => $missing,
                ]);

                throw new Exception('Callouts are temporarily unavailable because the alerting system is not fully configured. Please contact an administrator.');
            }

            // A callout must not be created if a provider can't afford to send the alerts.
            // Blocks if EITHER the primary or backup balance is below its minimum; an
            // unknown/unreachable balance never blocks (see SmsBalanceService).
            $lowCredit = app(\App\Services\Sms\SmsBalanceService::class)->blockingProviders();

            if (!empty($lowCredit)) {
                Log::critical('Callout creation blocked: SMS credit below minimum.', [
                    'providers' => $lowCredit,
                ]);

                // Loud alert: auto-top-up should make this impossible, so if a callout is
                // ever actually blocked for low credit we want to know about it immediately.
                try {
                    $which = implode(' and ', $lowCredit);
                    SlackAlert::to('callouts-overdue')->message("🚨 *Callout BLOCKED — SMS credit too low* ({$which}). Auto-top-up should prevent this; check the provider account(s) now.");
                } catch (\Throwable $e) {
                    Log::error('Failed to send low-credit Slack alert: '.$e->getMessage());
                }

                throw new Exception('Callouts are temporarily unavailable because the alerting credit is too low. Please contact an administrator.');
            }
        }

        $calloutTime = Carbon::parse($data['callout_time'])->utc();

        if (!OnCallShift::isCovered($calloutTime)) {
            throw new Exception('Cannot create callout: No administrator is on-call at '.$calloutTime->toDateTimeString());
        }

        // Resolve hidden phone numbers for registered users
        if (!empty($data['participants'])) {
            foreach ($data['participants'] as &$p) {
                if (($p['phone'] ?? '') === '🔒 Hidden') {
                    if (!empty($p['user_id'])) {
                        $p['phone'] = User::find($p['user_id'])?->phone;
                    } else {
                        $p['phone'] = null;
                    }
                }
            }
            unset($p);
        }

        // Collect all checking phones
        $phonesToCheck = collect($data['participants'] ?? [])->pluck('phone')->filter();
        if ($user->phone) {
            $phonesToCheck->push($user->phone);
        }

        // Also fetch phones for any user_ids provided in participants if phone is missing?
        // For now, rely on provided phones or strictly enforce "One active callout per person"

        // Compare on the normalised digit suffix (same convention as the Twilio webhook)
        // so formatting differences — "+447700900123" vs "07700 900 123" — still match.
        $phoneSuffixes = $phonesToCheck
            ->map(fn ($phone) => $this->phoneSuffix((string) $phone))
            ->filter()
            ->unique()
            ->values();

        if ($phoneSuffixes->isNotEmpty()) {
            $matchesPhoneSuffix = function ($q) use ($phoneSuffixes) {
                $q->where(function ($q) use ($phoneSuffixes) {
                    foreach ($phoneSuffixes as $suffix) {
                        $q->orWhere('phone', 'like', "%{$suffix}");
                    }
                });
            };

            $existingCallout = Callout::query()
                ->whereIn('status', ['active', 'triggered'])
                ->where(function ($query) use ($matchesPhoneSuffix) {
                    $query->whereHas('participants', $matchesPhoneSuffix)
                        ->orWhereHas('user', $matchesPhoneSuffix);
                })
                ->first();

            if ($existingCallout) {
                throw new Exception('One or more participants (or you) are already in an active callout. Please resolve the existing callout first.');
            }
        }

        $callout = DB::transaction(function () use ($user, $data, $calloutTime) {
            $callout = Callout::create([
                'id' => str()->random(16),
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
                        // Normalised on write so the SMS webhook's suffix matching
                        // ("OUT SAFE" during a live rescue) can always find them.
                        'phone' => $this->normalizePhone($p['phone'] ?? null),
                        'email' => $p['email'] ?? null,
                    ]);
                }
            }

            // Backup coverage is mandatory: a callout must be watched by BOTH the
            // primary Subterra scheduler AND the independent GCP backup watchdog. If
            // the backup cannot be registered we roll the whole creation back and
            // surface the error, rather than create a callout that only one system is
            // watching. Registering inside the transaction means a failure leaves no
            // callout behind, and a successful registration is the last external step
            // before commit, so a rolled-back callout never orphans a backup entry.
            // When the watchdog is not configured (e.g. local development or CI) we
            // skip it and create the callout without backup coverage.
            if ($this->watchdogService->isConfigured()) {
                $watchdogId = $this->watchdogService->register($callout);

                if ($watchdogId === null) {
                    Log::error('GCP Watchdog registration failed; rolling back callout creation so it is never watched by only one system.', [
                        'user_id' => $user->id,
                    ]);

                    throw new Exception('We could not register this callout with the backup safety system, so it was not created. Please try again in a moment. If the problem continues, leave your plans with a trusted person and contact a duty officer directly.');
                }

                $callout->update(['watchdog_registered_at' => now()]);
            }

            return $callout;
        });

        // Send participant emails only after the transaction has committed: synchronous
        // SMTP calls must not hold DB locks, and nobody should be emailed about a callout
        // that ends up rolled back. Each recipient is isolated so one failing address
        // can never block the rest, and no email problem may ever fail the (already
        // committed) callout.
        try {
            // Collect all emails, falling back to User account if autocomplete only sent user_id
            $emails = collect($callout->refresh()->load('participants.user')->participants)
                ->map(fn ($p) => $p->email ?? $p->user?->email)
                ->filter();

            if ($user->email) {
                $emails->push($user->email);
            }

            $emails->unique()->each(function ($email) use ($callout) {
                try {
                    Mail::to($email)->send(new CalloutStarted($callout));
                } catch (Exception $e) {
                    Log::error('Email Failure creating callout: '.$e->getMessage());
                }
            });
        } catch (Exception $e) {
            Log::error('Email Failure creating callout: '.$e->getMessage());
        }

        // Dispatched after the transaction commits so its synchronous listeners
        // (e.g. the Slack alert) can never roll back a successfully created callout.
        CalloutCreated::dispatch($callout);

        return $callout;
    }

    /**
     * Return the list of essential config keys that are currently empty. A callout
     * cannot safely be created while any of these are missing (see config/callouts.php
     * `required_config`).
     *
     * @return array<int, string>
     */
    public function missingEssentialConfig(): array
    {
        return collect(config('callouts.required_config', []))
            ->filter(fn ($key) => empty(config($key)))
            ->values()
            ->all();
    }

    /**
     * Cancel a callout (Mark as resolved/safe).
     *
     * $source describes where the cancellation came from (e.g. 'App', 'SMS') and is
     * recorded in the incident audit note when a rescue is already underway.
     */
    public function cancel(Callout $callout, string $source = 'App'): ?Trip
    {
        // Idempotency guard: a callout can only be finished once. Without this,
        // repeated cancel requests (e.g. an anxious caver double-tapping "I am safe")
        // would each create a duplicate Trip and re-send cancellation emails. Once
        // the callout is cancelled — or resolved by an admin closing the incident —
        // treat further cancels as no-ops.
        if (in_array($callout->status, self::FINISHED_STATUSES, true)) {
            return null;
        }

        // Atomic gate: re-check the status under a row lock inside a transaction so two
        // concurrent cancels can't both pass the (stale, in-memory) guard above and each
        // create a Trip + email blast. Only the request holding the lock proceeds, and a
        // mid-way failure rolls the Trip back rather than leaving it behind with the
        // callout still active.
        $trip = DB::transaction(function () use ($callout, $source): ?Trip {
            $locked = Callout::query()->whereKey($callout->getKey())->lockForUpdate()->first();

            if (!$locked || in_array($locked->status, self::FINISHED_STATUSES, true)) {
                return null;
            }

            $trip = $this->createTripFromCallout($callout);

            // Mark as cancelled (instead of deleting)
            $callout->update(['status' => 'cancelled']);

            if ($callout->incident()->exists()) {
                // DO NOT DELETE the incident if rescue is underway.
                // Mark user as safe but leave incident for admin to close.
                $callout->incident->notes()->create([
                    'user_id' => null, // System note
                    'content' => "USER MARKED THEMSELVES SAFE via {$source}. Please verify and resolve incident.",
                ]);
            }

            return $trip;
        });

        // Another request already finished this callout — nothing more to do.
        if ($trip === null) {
            return null;
        }

        try {
            $this->watchdogService->cancel($callout);
        } catch (Exception $e) {
            Log::error('GCP Watchdog cancellation failed: '.$e->getMessage());
            // Continue with cancellation even if watchdog fails
        }

        try {
            // Ensure participants and attached users are loaded
            $callout->loadMissing('participants.user');

            // Collect all emails
            $emails = collect($callout->participants ?? [])
                ->map(fn ($p) => $p->email ?? $p->user?->email)
                ->filter();

            if ($callout->user && $callout->user->email) {
                $emails->push($callout->user->email);
            }

            $emails->unique()->each(function ($email) use ($callout) {
                Mail::to($email)->send(new CalloutCancelledMail($callout));
            });
        } catch (Exception $e) {
            Log::error('Email Failure cancelling callout: '.$e->getMessage());
        }

        if (!$callout->incident()->exists()) {
            CalloutCancelled::dispatch($callout);
        }

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
     * Normalise a phone number for storage: keep digits and a single leading "+"
     * (international prefix), stripping spaces and formatting characters, so stored
     * numbers can always be matched by the suffix comparison used in the Twilio
     * webhook and the duplicate-callout check.
     */
    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        $normalized = (str_starts_with($normalized, '+') ? '+' : '').str_replace('+', '', $normalized);

        return in_array($normalized, ['', '+'], true) ? null : $normalized;
    }

    /**
     * The last (up to) 10 digits of a phone number — the same convention the Twilio
     * webhook uses to match inbound numbers regardless of +44/0 prefix or formatting.
     */
    private function phoneSuffix(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
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
