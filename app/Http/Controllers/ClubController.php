<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ClubAccessRequested;
use App\Events\ClubAccessResponded;
use App\Http\Resources\ClubDetailResource;
use App\Http\Resources\ClubResource;
use App\Http\Resources\UserDetailEmailResource;
use App\Models\Club;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClubController extends Controller
{
    /**
     * Display a listing of enabled clubs (Public).
     */
    public function index(): ResourceCollection
    {
        $clubs = Club::withCount(['approvedUsers as users_count'])
                     ->where('is_active', true)
                     ->orderBy('name')
                     ->get();

        return ClubResource::collection($clubs);
    }

    /**
     * Display a listing of all clubs (Admin).
     * Includes both enabled and disabled clubs.
     */
    public function adminIndex(): ResourceCollection
    {
        $clubs = Club::withCount(['approvedUsers as users_count'])
                     ->orderBy('name')
                     ->get();

        return ClubResource::collection($clubs);
    }

    /**
     * Display the specified club.
     */
    public function show(Club $club): JsonResponse
    {
        if (!$club->is_active && !(auth()->check() && auth()->user()->is_admin)) {
            return response()->json(['message' => 'Club not found or access denied.'], 404);
        }
        $club->loadCount(['approvedUsers as users_count']);

        if (auth()->check()) {
            $user = auth()->user();
            $isClubAdmin = $user->is_admin || $club->users()->where('user_id', $user->id)->wherePivot('is_admin', true)->exists();
            if ($isClubAdmin) {
                $club->loadCount('pendingUsers');
            }
        }

        $club->load('huts');

        return response()->json(new ClubDetailResource($club));
    }

    /**
     * Store a newly created club (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:clubs,name',
            'slug' => 'required|string|max:255|unique:clubs,slug',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean', // Default to true if not provided
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $validatedData['is_active'] = $request->input('is_active', true);

        $club = Club::create($validatedData);
        $club->loadCount(['approvedUsers as users_count']);

        return response()->json(new ClubDetailResource($club), 201);
    }

    /**
     * Update the specified club (Admin).
     */
    public function update(Request $request, Club $club): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('clubs')->ignore($club->id),
            ],
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $club->update($validator->validated());

        return response()->json(new ClubDetailResource($club->fresh()->loadCount(['approvedUsers as users_count'])));
    }

    /**
     * Remove the specified club from storage (Admin).
     */
    public function destroy(Club $club): JsonResponse
    {
        try {
            $club->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting club.'], 500);
        }
    }

    /**
     * Toggle the enabled status of the specified club (Admin).
     */
    public function toggleActive(Club $club): JsonResponse
    {
        $club->is_active = !$club->is_active;
        $club->save();

        return response()->json(new ClubDetailResource($club->fresh()->loadCount(['approvedUsers as users_count'])));
    }

    /**
     * Allow authenticated user to request joining a club.
     * Creates a pending membership record.
     */
    public function requestJoin(Club $club): JsonResponse
    {
        $user = Auth::user();

        $existing = $club->users()->where('user_id', $user->id)->first();

        if ($existing) {
            return response()->json(['message' => 'You are already a member or your request is pending.'], 409);
        }

        try {
            $club->users()->attach($user->id, ['status' => 'pending']);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Check-then-attach race (e.g. a double-click): the row already
            // exists, so return the same conflict response instead of a 500.
            return response()->json(['message' => 'You are already a member or your request is pending.'], 409);
        }

        event(new ClubAccessRequested($club, $user));

        return response()->json(['message' => 'Join request sent successfully.'], 201);
    }

    /**
     * Get the *approved* members of a specific club (Admin).
     */
    public function getApprovedMembers(Club $club): JsonResponse
    {
        $members = $club->approvedUsers()->orderBy('name')->get();

        return response()->json($members->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_club_admin' => (bool) $user->pivot->is_admin,
            ];
        }));
    }

    /**
     * Sync *approved* members and their admin status for a specific club (Admin).
     */
    public function syncApprovedMembers(Request $request, Club $club): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'members' => 'present|array',
            'members.*.id' => 'required|exists:users,id',
            'members.*.is_admin' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $membersData = $request->input('members');
        $syncData = [];
        foreach ($membersData as $member) {
            $syncData[$member['id']] = [
                'is_admin' => $member['is_admin'],
                'status' => 'approved',
            ];
        }

        try {
            DB::transaction(function () use ($club, $syncData) {
                // Diff against the unfiltered relation: a member in the list
                // who currently has a pending row must be promoted via an
                // update, not re-attached (which would hit the unique index).
                $currentStatuses = $club->users()->pluck('club_user.status', 'users.id');

                // Detach approved members omitted from the list. Pending
                // requests are managed separately and stay untouched.
                $removeIds = $currentStatuses
                    ->filter(fn ($status) => $status === 'approved')
                    ->keys()
                    ->diff(array_keys($syncData));

                if ($removeIds->isNotEmpty()) {
                    $club->users()->detach($removeIds->all());
                }

                foreach ($syncData as $userId => $pivot) {
                    if ($currentStatuses->has($userId)) {
                        $club->users()->updateExistingPivot($userId, $pivot);
                    } else {
                        $club->users()->attach($userId, $pivot);
                    }
                }
            });

            return $this->getApprovedMembers($club);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating club members.'], 500);
        }
    }

    /**
     * Get pending membership requests for a club (Admin).
     */
    public function getPendingMembers(Club $club): JsonResponse
    {
        $pending = $club->users()->wherePivot('status', 'pending')->get();

        return response()->json($pending->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        }));
    }

    public function approveMember(Club $club, User $user): UserDetailEmailResource
    {
        // Only a genuinely pending request can be approved — otherwise the
        // update is a silent no-op yet the "approved" email would still fire.
        $isPending = $club->users()
            ->where('user_id', $user->id)
            ->wherePivot('status', 'pending')
            ->exists();

        if (!$isPending) {
            abort(422, 'User has no pending membership request for this club.');
        }

        $club->users()->updateExistingPivot($user->id, ['status' => 'approved']);
        event(new ClubAccessResponded($club, $user, 'approved'));

        return new UserDetailEmailResource($user->fresh());
    }

    public function rejectMember(Request $request, Club $club, User $user): JsonResponse
    {
        $isPending = $club->users()
            ->where('user_id', $user->id)
            ->wherePivot('status', 'pending')
            ->exists();

        if (!$isPending) {
            return response()->json(['message' => 'User has no pending membership request for this club.'], 422);
        }

        $club->users()->detach($user->id);
        $reason = $request->input('reason');
        event(new ClubAccessResponded($club, $user, 'rejected', $reason));

        return response()->json(['message' => 'Member rejected.']);
    }
}
