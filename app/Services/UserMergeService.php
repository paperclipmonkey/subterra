<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Merges one user account into another: reassigns trips, club/medal/role/permit
 * memberships, bookings, callouts, incidents, on-call shifts, collections, pages,
 * pip feedback, suggested edits, SMS history, and audit/API-interaction history
 * from the source account to the target, then deletes the source.
 *
 * Used when someone loses access to an old account and signs up again, ending up
 * with two profiles for the same person. Profile fields (name, email, bio, phone,
 * photo) are left untouched on the target — only relational data is transferred.
 */
class UserMergeService
{
    /**
     * @throws \InvalidArgumentException when source and target are the same user
     * @throws \Throwable on failure (transaction is rolled back)
     */
    public function merge(User $target, User $source): void
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot merge a user into themselves.');
        }

        DB::beginTransaction();

        try {
            $this->mergeTrips($target, $source);
            $this->mergeClubMemberships($target, $source);
            $this->mergeMedals($target, $source);
            $this->mergeSimplePivot('role_user', 'role_id', $target, $source);
            $this->mergeSimplePivot('permit_user', 'permit_id', $target, $source);

            Permit::where('created_by', $source->id)->update(['created_by' => $target->id]);
            Booking::where('user_id', $source->id)->update(['user_id' => $target->id]);
            Booking::where('approved_by', $source->id)->update(['approved_by' => $target->id]);

            DB::table('collections')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            $this->mergeCalloutParticipants($target, $source);

            DB::table('callouts')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('incidents')->where('incident_controller_id', $source->id)->update(['incident_controller_id' => $target->id]);
            DB::table('incident_notes')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('on_call_shifts')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('pages')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('pip_feedback')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('suggested_edits')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('sms_messages')->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('audits')->where('user_type', User::class)->where('user_id', $source->id)->update(['user_id' => $target->id]);
            DB::table('api_interactions')->where('trackable_type', User::class)->where('trackable_id', $source->id)->update(['trackable_id' => $target->id]);

            // Delete the source's uploaded photo (mirrors account-deletion cleanup) —
            // the target's own photo is never touched.
            if ($source->photo &&
                !str_contains($source->photo, 'default.webp') &&
                !str_contains($source->photo, 'default.png') &&
                !str_starts_with($source->photo, 'http')
            ) {
                Storage::disk('media')->delete($source->photo);
            }

            // All relations have been migrated above — safe to hard-delete now.
            // Any remaining pivot rows (there shouldn't be any) cascade-delete.
            $source->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Move trip participation (trip_user pivot) from source to target.
     * If both are already participants of the same trip, drop the source's
     * duplicate row rather than violating the pivot's unique constraint.
     */
    private function mergeTrips(User $target, User $source): void
    {
        $targetTripIds = DB::table('trip_user')->where('user_id', $target->id)->pluck('trip_id');
        $sourceRows = DB::table('trip_user')->where('user_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            if ($targetTripIds->contains($row->trip_id)) {
                DB::table('trip_user')
                    ->where('trip_id', $row->trip_id)
                    ->where('user_id', $source->id)
                    ->delete();
            } else {
                DB::table('trip_user')
                    ->where('trip_id', $row->trip_id)
                    ->where('user_id', $source->id)
                    ->update(['user_id' => $target->id]);
            }
        }
    }

    /**
     * Move club memberships (club_user pivot) from source to target.
     * When both belong to the same club already, the memberships are combined
     * (admin/approved status wins) and the source's row is dropped.
     */
    private function mergeClubMemberships(User $target, User $source): void
    {
        $targetRows = DB::table('club_user')->where('user_id', $target->id)->get()->keyBy('club_id');
        $sourceRows = DB::table('club_user')->where('user_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            $existing = $targetRows->get($row->club_id);

            if ($existing) {
                DB::table('club_user')->where('id', $existing->id)->update([
                    'is_admin' => $existing->is_admin || $row->is_admin,
                    'status' => ($existing->status === 'approved' || $row->status === 'approved')
                        ? 'approved'
                        : $existing->status,
                ]);
                DB::table('club_user')->where('id', $row->id)->delete();
            } else {
                DB::table('club_user')->where('id', $row->id)->update(['user_id' => $target->id]);
            }
        }
    }

    /**
     * Move medal awards (medal_user pivot) from source to target.
     * When both already hold the same medal, the earliest award date is kept
     * and the source's duplicate row is dropped.
     */
    private function mergeMedals(User $target, User $source): void
    {
        $targetRows = DB::table('medal_user')->where('user_id', $target->id)->get()->keyBy('medal_id');
        $sourceRows = DB::table('medal_user')->where('user_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            $existing = $targetRows->get($row->medal_id);

            if ($existing) {
                if ($row->awarded_at && (!$existing->awarded_at || $row->awarded_at < $existing->awarded_at)) {
                    DB::table('medal_user')->where('id', $existing->id)->update(['awarded_at' => $row->awarded_at]);
                }
                DB::table('medal_user')->where('id', $row->id)->delete();
            } else {
                DB::table('medal_user')->where('id', $row->id)->update(['user_id' => $target->id]);
            }
        }
    }

    /**
     * Move rows of a simple user_id/other_id pivot (no extra pivot columns
     * worth preserving beyond timestamps) from source to target, dropping
     * the source's row when the target already has that same relation.
     */
    private function mergeSimplePivot(string $table, string $otherKey, User $target, User $source): void
    {
        $targetIds = DB::table($table)->where('user_id', $target->id)->pluck($otherKey);
        $sourceRows = DB::table($table)->where('user_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            if ($targetIds->contains($row->$otherKey)) {
                DB::table($table)
                    ->where($otherKey, $row->$otherKey)
                    ->where('user_id', $source->id)
                    ->delete();
            } else {
                DB::table($table)
                    ->where($otherKey, $row->$otherKey)
                    ->where('user_id', $source->id)
                    ->update(['user_id' => $target->id]);
            }
        }
    }

    /**
     * Move linked callout_participants rows from source to target. If the
     * target is already a linked participant on the same callout, the
     * source's duplicate row is dropped instead of creating two entries for
     * the same person on one callout.
     */
    private function mergeCalloutParticipants(User $target, User $source): void
    {
        $targetCalloutIds = DB::table('callout_participants')->where('user_id', $target->id)->pluck('callout_id');
        $sourceRows = DB::table('callout_participants')->where('user_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            if ($targetCalloutIds->contains($row->callout_id)) {
                DB::table('callout_participants')->where('id', $row->id)->delete();
            } else {
                DB::table('callout_participants')->where('id', $row->id)->update(['user_id' => $target->id]);
            }
        }
    }
}
