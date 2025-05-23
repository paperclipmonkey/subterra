<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\ClubResource;
use App\Http\Resources\ClubDetailResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Events\ClubAccessRequested;
use App\Events\ClubAccessResponded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Js;

class ClubController extends Controller
{
    /**
     * Display a listing of enabled clubs (Public).
     */
    public function index(): ResourceCollection
    {
        $clubs = Club::withCount('users')
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
        $clubs = Club::withCount('users')
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
        // Load count for detail view
        $club->loadCount('users');
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
        // Set default enabled status if not provided
        $validatedData['is_active'] = $request->input('is_active', true);

        $club = Club::create($validatedData);
        $club->loadCount('users'); // Load count for the response

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
        $club->loadCount('users');
        return response()->json(new ClubDetailResource($club->fresh()->loadCount('users')));
    }

    /**
     * Remove the specified club from storage (Admin).
     */
    public function destroy(Club $club): JsonResponse
    {
        // Check if the club is associated with any users
        // If so, consider implications of deleting this club
        // For now, we will just delete it.

        try {
            $club->delete();
            return response()->json(null, 204); // No Content
        } catch (\Exception $e) {
            // Log error $e->getMessage()
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
        return response()->json(new ClubDetailResource($club->fresh()->loadCount('users')));
    }

    /**
     * Allow authenticated user to request joining a club.
     * Creates a pending membership record.
     */
    public function requestJoin(Club $club): JsonResponse
    {
        $user = Auth::user();

        // Check if already a member or pending
        $existing = $club->users()->where('user_id', $user->id)->first();

        if ($existing) {
            return response()->json(['message' => 'You are already a member or your request is pending.'], 409); // Conflict
        }

        // Attach user with pending status
        $club->users()->attach($user->id, ['status' => 'pending']);

        // Dispatch event for notification
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
            'members.*.id' => 'required|integer|exists:users,id',
            'members.*.is_admin' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $membersData = $request->input('members');
        $syncData = [];
        foreach ($membersData as $member) {
            // Prepare data for sync, ensuring status is 'approved'
            $syncData[$member['id']] = [
                'is_admin' => $member['is_admin'],
                'status' => 'approved' // Explicitly set status for synced members
            ];
        }

        try {
            // Sync only approved members. This will detach any users not in the list.
            // Be careful if you want to preserve pending/rejected statuses - this might remove them.
            // A more nuanced approach might be needed if preserving non-approved statuses during sync is required.
            // For now, this assumes the sync list represents the desired *approved* members.
            $club->approvedUsers()->sync($syncData); // Sync against the approvedUsers relationship

            // Return the updated list of approved members
            return $this->getApprovedMembers($club);

        } catch (\Exception $e) {
            // Log error $e->getMessage()
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

    public function approveMember(Club $club, User $user): JsonResponse
    {
        // Only approve if currently pending
        $club->users()->updateExistingPivot($user->id, ['status' => 'approved']);
        // Dispatch event for notification
        event(new ClubAccessResponded($club, $user, 'approved'));
        return response()->json(['message' => 'Member approved.']);
    }

    public function rejectMember(Club $club, User $user): JsonResponse
    {
        // Remove the pending membership
        $club->users()->detach($user->id);
        // Dispatch event for notification
        event(new ClubAccessResponded($club, $user, 'rejected'));
        return response()->json(['message' => 'Member rejected.']);
    }
}
