<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Callout;
use App\Models\CalloutParticipant;
use App\Models\Collection;
use App\Models\IncidentNote;
use App\Models\OnCallShift;
use App\Models\PipFeedback;
use App\Models\Report;
use App\Models\SmsMessage;
use App\Models\SuggestedEdit;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything Subterra holds about one user, for the "Download my data" button
 * (a UK GDPR subject access / portability request).
 *
 * Content other people wrote is left out (e.g. reports filed *about* the user,
 * which could identify the reporter). Contact details the user entered for other
 * callout participants are reduced to names.
 */
class UserDataExportService
{
    /** Never exported: secrets, not personal data about the user. */
    private const PROFILE_EXCLUDE = ['phone_verification_code'];

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $user->loadMissing(['clubs', 'medals', 'roles']);

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => collect($user->attributesToArray())->except(self::PROFILE_EXCLUDE)->all(),
            'roles' => $user->roles->pluck('name')->values(),
            // getAttribute()/getRelation(): these relations aren't generically typed.
            'clubs' => $user->clubs->map(fn ($club) => [
                'name' => $club->getAttribute('name'),
                'status' => $club->getRelation('pivot')->getAttribute('status'),
                'is_admin' => (bool) $club->getRelation('pivot')->getAttribute('is_admin'),
            ])->values(),
            'medals' => $user->medals->map(fn ($medal) => [
                'name' => $medal->getAttribute('name'),
                'description' => $medal->getAttribute('description'),
                'awarded_at' => $medal->getRelation('pivot')->getAttribute('awarded_at'),
            ])->values(),
            'trips' => $this->trips($user),
            'callouts' => $this->calloutsCreated($user),
            'callouts_listed_on' => $this->calloutsListedOn($user),
            'bookings' => Booking::with('permit:id,name')->where('user_id', $user->id)->get()->map(fn ($b) => [
                'permit' => $b->permit?->getAttribute('name'),
                'date' => $b->date,
                'participants' => $b->participants,
                'status' => $b->status,
                'notes' => $b->notes,
                'rejection_reason' => $b->rejection_reason,
                'created_at' => $b->created_at,
            ])->values(),
            'collections' => Collection::withCount('caves')->where('user_id', $user->id)->get()->map(fn ($c) => [
                'name' => $c->name,
                'description' => $c->description,
                'photo_path' => $c->photo_path,
                'cave_count' => $c->caves_count,
                'created_at' => $c->created_at,
            ])->values(),
            'suggested_edits' => SuggestedEdit::where('user_id', $user->id)->get()->map(fn ($e) => [
                'target' => class_basename((string) $e->suggestable_type).' '.$e->suggestable_id,
                'suggested_data' => $e->suggested_data,
                'status' => $e->status,
                'admin_comment' => $e->admin_comment,
                'created_at' => $e->created_at,
            ])->values(),
            'reports_filed' => Report::where('reporter_id', $user->id)->get()->map(fn ($r) => [
                'about' => class_basename((string) $r->reportable_type).' '.$r->reportable_id,
                'category' => $r->category,
                'details' => $r->details,
                'status' => $r->status,
                'created_at' => $r->created_at,
            ])->values(),
            'pip_feedback' => PipFeedback::where('user_id', $user->id)->get()->map(fn ($f) => [
                'rating' => $f->rating,
                'comment' => $f->comment,
                'transcript' => $f->transcript,
                'created_at' => $f->created_at,
            ])->values(),
            'sms_sent_to_you' => SmsMessage::where('user_id', $user->id)->get()->map(fn ($m) => [
                'context' => $m->context,
                'status' => $m->status,
                'sent_at' => $m->sent_at,
                'delivered_at' => $m->delivered_at,
            ])->values(),
            'duty_officer_shifts' => OnCallShift::where('user_id', $user->id)->orderBy('start_at')->get(['start_at', 'end_at']),
            'incident_notes_written' => IncidentNote::where('user_id', $user->id)->get(['incident_id', 'content', 'created_at']),
            'account_history' => $this->accountHistory($user),
        ];
    }

    private function trips(User $user)
    {
        return Trip::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with(['system:id,name', 'entrance:id,name', 'exit:id,name', 'participants:id,name', 'media'])
            ->orderBy('start_time')
            ->get()
            ->map(fn ($trip) => [
                'id' => $trip->short_id,
                'name' => $trip->name,
                'description' => $trip->description,
                'cave_system' => $trip->system?->name,
                'entrance' => $trip->entrance?->name,
                'exit' => $trip->exit?->name,
                'start_time' => $trip->start_time,
                'end_time' => $trip->end_time,
                'visibility' => $trip->visibility,
                'participants' => $trip->participants->pluck('name')->all(),
                'photos' => $trip->media->map(fn ($m) => [
                    'url' => $m->getAttribute('url'),
                    'title' => $m->title,
                    'photographer' => $m->photographer,
                    'copyright' => $m->copyright,
                    'taken_at' => $m->taken_at,
                ])->all(),
            ])
            ->values();
    }

    private function calloutsCreated(User $user)
    {
        return Callout::with('participants')->where('user_id', $user->id)->get()->map(fn ($c) => [
            'id' => $c->id,
            'cave' => $c->cave_name,
            'callout_time' => $c->callout_time,
            'status' => $c->status,
            'trip_plan' => $c->trip_plan,
            'description' => $c->description,
            'team_details' => $c->team_details,
            'car_registration' => $c->car_registration,
            'car_parking' => $c->car_parking,
            'location_data' => $c->location_data,
            // Other people's contact details aren't the requester's data: names only.
            'participants' => $c->participants->pluck('name')->values(),
            'created_at' => $c->created_at,
        ])->values();
    }

    private function calloutsListedOn(User $user)
    {
        return CalloutParticipant::with('callout')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn ($p) => $p->callout && $p->callout->getAttribute('user_id') !== $user->id)
            ->map(fn ($p) => [
                'callout_id' => $p->callout_id,
                'cave' => $p->callout->getAttribute('cave_name'),
                'callout_time' => $p->callout->getAttribute('callout_time'),
                'status' => $p->callout->getAttribute('status'),
                'your_name_as_listed' => $p->name,
                'your_phone_as_listed' => $p->phone,
                'your_email_as_listed' => $p->email,
            ])->values();
    }

    /**
     * Changes to the user's own account record (old/new values, IP, user agent).
     */
    private function accountHistory(User $user)
    {
        return DB::table('audits')
            ->where('auditable_type', User::class)
            // auditable_id is a string column: compare as a string (Postgres).
            ->where('auditable_id', (string) $user->id)
            ->orderBy('created_at')
            ->get(['event', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'])
            ->map(fn ($a) => [
                'event' => $a->event,
                'old_values' => json_decode((string) $a->old_values, true),
                'new_values' => json_decode((string) $a->new_values, true),
                'ip_address' => $a->ip_address,
                'user_agent' => $a->user_agent,
                'at' => $a->created_at,
            ]);
    }
}
